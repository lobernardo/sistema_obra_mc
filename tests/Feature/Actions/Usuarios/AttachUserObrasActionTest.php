<?php

use App\Actions\Usuarios\AttachUserObrasAction;
use App\Enums\UserAdminAction;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = app(AttachUserObrasAction::class);
});

/**
 * @return array<string, list<string>>
 */
function attachValidationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);

        return $exception->errors();
    }

    test()->fail('A ValidationException was expected.');
}

/**
 * @return list<int>
 */
function sortedIds(Obra ...$obras): array
{
    return collect($obras)->pluck('id')->sort()->values()->all();
}

test('adding [A, B, C] to a user with no obra creates exactly 3 rows and one audit (RF-09, RF-12)', function (string $actorState, string $targetState) {
    $actor = User::factory()->{$actorState}()->create();
    $target = User::factory()->{$targetState}()->create();
    [$obraA, $obraB, $obraC] = Obra::factory()->count(3)->create();

    $this->action->execute($actor, $target, ['obra_ids' => [$obraA->id, $obraB->id, $obraC->id]]);

    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(3);

    $audit = UserAdminEvent::query()->sole();

    expect($audit->action)->toBe(UserAdminAction::ObraAccessChanged);
    expect($audit->actor_id)->toBe($actor->id);
    expect($audit->target_id)->toBe($target->id);
    expect($audit->before)->toBe(['obra_ids' => []]);
    expect($audit->after)->toBe(['obra_ids' => sortedIds($obraA, $obraB, $obraC)]);
})->with([
    'gestao → obra' => ['gestao', 'obra'],
    'suprimentos → obra' => ['suprimentos', 'obra'],
    'gestao → suprimentos' => ['gestao', 'suprimentos'],
]);

test('a Suprimentos actor adding [A, B] writes one record with actor Suprimentos, before [] and after [A, B] (RF-12)', function () {
    $actor = User::factory()->suprimentos()->create();
    $target = User::factory()->obra()->create();
    [$obraA, $obraB] = Obra::factory()->count(2)->create();

    $this->action->execute($actor, $target, ['obra_ids' => [$obraB->id, $obraA->id]]);

    $audit = UserAdminEvent::query()->sole();

    expect($audit->actor_id)->toBe($actor->id);
    expect($audit->before)->toBe(['obra_ids' => []]);
    expect($audit->after)->toBe(['obra_ids' => sortedIds($obraA, $obraB)]);
});

test('a failure on the 3rd insert rolls everything back: 0 rows and 0 audits', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $obraIds = Obra::factory()->count(3)->create()->modelKeys();
    $inserts = 0;

    DB::connection()->beforeExecuting(function (string $query) use (&$inserts): void {
        if (str_starts_with($query, 'insert into "obra_profile"') && ++$inserts === 3) {
            throw new RuntimeException('Falha simulada no 3º insert');
        }
    });

    expect(fn () => $this->action->execute($actor, $target, ['obra_ids' => $obraIds]))
        ->toThrow(RuntimeException::class, 'Falha simulada no 3º insert');

    expect($inserts)->toBe(3);
    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(0);
    expect(UserAdminEvent::query()->count())->toBe(0);
});

test('an obra already associated rejects the whole operation with a 422 naming it (RF-10)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $obraA = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $obraB = Obra::factory()->create();
    $target->obras()->attach($obraA->id);

    $errors = attachValidationErrors(fn () => $this->action->execute($actor, $target, ['obra_ids' => [$obraB->id, $obraA->id]]));

    expect($errors['obra_ids'])->toBe(['A obra «Residencial Aurora» já está associada a este usuário.']);
    expect($target->obras()->pluck('obras.id')->all())->toBe([$obraA->id]);
    expect(UserAdminEvent::query()->count())->toBe(0);
});

test('[A] already associated yields 422 and 0 new rows', function () {
    $actor = User::factory()->suprimentos()->create();
    $target = User::factory()->obra()->create();
    $obraA = Obra::factory()->create();
    $target->obras()->attach($obraA->id);

    attachValidationErrors(fn () => $this->action->execute($actor, $target, ['obra_ids' => [$obraA->id]]));

    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(1);
});

test('the same obra twice in the input is rejected with a 422 naming it and nothing is written (RF-10)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $obra = Obra::factory()->create(['name' => 'Edifício Solar']);

    $errors = attachValidationErrors(fn () => $this->action->execute($actor, $target, ['obra_ids' => [$obra->id, $obra->id]]));

    expect($errors['obra_ids'])->toBe(['A obra «Edifício Solar» foi informada mais de uma vez.']);
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect(UserAdminEvent::query()->count())->toBe(0);
});

/**
 * RF-10 race: the duplicate check passes, then a concurrent request commits
 * the same association before our INSERT. The rival row is written right
 * after the check query, outside the Action's transaction, so it survives
 * the savepoint rollback exactly like a committed concurrent request would.
 */
test('a concurrent duplicate reaching the composite primary key surfaces as 422, never 500, with exactly 1 row', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $obra = Obra::factory()->create(['name' => 'Obra Corrida']);
    $injected = false;

    DB::listen(function (QueryExecuted $query) use (&$injected, $target, $obra): void {
        if ($injected || ! str_contains($query->sql, '"obra_profile"') || ! str_starts_with($query->sql, 'select')) {
            return;
        }

        $injected = true;

        DB::table('obra_profile')->insert(['obra_id' => $obra->id, 'user_id' => $target->id, 'created_at' => now()]);
    });

    $errors = attachValidationErrors(fn () => $this->action->execute($actor, $target, ['obra_ids' => [$obra->id]]));

    expect($injected)->toBeTrue();
    expect($errors['obra_ids'])->toBe(['A obra «Obra Corrida» já está associada a este usuário.']);
    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(1);
    expect(UserAdminEvent::query()->count())->toBe(0);
});

test('obras in any status, including Concluída, are accepted (NC-07)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $obras = collect([
        Obra::factory()->aIniciar()->create(),
        Obra::factory()->emAndamento()->create(),
        Obra::factory()->concluida()->create(),
    ]);

    $obraIds = $obras->pluck('id')->sort()->values()->all();

    $this->action->execute($actor, $target, ['obra_ids' => $obraIds]);

    expect($target->obras()->pluck('obras.id')->sort()->values()->all())->toBe($obraIds);
});

test('an empty, missing or invalid obra_ids list is rejected and nothing is written', function (array $data) {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();

    $errors = attachValidationErrors(fn () => $this->action->execute($actor, $target, $data));

    expect(array_keys($errors)[0])->toStartWith('obra_ids');
    expect(DB::table('obra_profile')->count())->toBe(0);
    expect(UserAdminEvent::query()->count())->toBe(0);
})->with([
    'missing' => [[]],
    'empty' => [['obra_ids' => []]],
    'unknown obra' => [['obra_ids' => [999999]]],
    'not an array' => [['obra_ids' => 'abc']],
]);

test('a gestao target is rejected with 422 on user_id (NC-03)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();

    $errors = attachValidationErrors(fn () => $this->action->execute($actor, $target, ['obra_ids' => [$obra->id]]));

    expect($errors['user_id'])->toBe(['Somente usuários dos perfis Obra e Suprimentos podem ter obras associadas.']);
    expect(DB::table('obra_profile')->count())->toBe(0);
});

test('an obra actor gets AuthorizationException and nothing is written (RF-07)', function () {
    $actor = User::factory()->obra()->create();
    $target = User::factory()->obra()->create();
    $obra = Obra::factory()->create();

    expect(fn () => $this->action->execute($actor, $target, ['obra_ids' => [$obra->id]]))
        ->toThrow(AuthorizationException::class, 'Apenas os perfis Gestão e Suprimentos podem administrar obras.');

    expect(DB::table('obra_profile')->count())->toBe(0);
    expect(UserAdminEvent::query()->count())->toBe(0);
});

test('a Suprimentos actor attaches an obra to its own account: +1 row, 1 audit with actor = target (RF-11 v1.3, F-13)', function () {
    $actor = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->create();

    $this->action->execute($actor, $actor, ['obra_ids' => [$obra->id]]);

    expect($actor->obras()->pluck('obras.id')->all())->toBe([$obra->id]);

    $audit = UserAdminEvent::query()->sole();

    expect($audit->action)->toBe(UserAdminAction::ObraAccessChanged);
    expect($audit->actor_id)->toBe($actor->id);
    expect($audit->target_id)->toBe($actor->id);
});

test('a Suprimentos actor attaches an obra to another Suprimentos user without error (RF-11 v1.3, F-13)', function () {
    $actor = User::factory()->suprimentos()->create();
    $target = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->create();

    $this->action->execute($actor, $target, ['obra_ids' => [$obra->id]]);

    expect($target->obras()->pluck('obras.id')->all())->toBe([$obra->id]);
    expect(UserAdminEvent::query()->where('actor_id', $actor->id)->where('target_id', $target->id)->count())->toBe(1);
});

test('associations of a suprimentos user neither restrict nor expand the pedidos it sees (RF-15)', function () {
    $status = Status::factory()->solicitado()->create();
    [$obraA, $obraB, $obraC] = Obra::factory()->count(3)->create();
    foreach ([$obraA, $obraB, $obraC] as $obra) {
        Pedido::factory()->for($obra)->for($status)->create();
    }

    $withoutObras = User::factory()->suprimentos()->create();
    $withObras = User::factory()->suprimentos()->create();
    $this->action->execute(User::factory()->gestao()->create(), $withObras, ['obra_ids' => [$obraA->id, $obraB->id]]);

    $all = Pedido::query()->pluck('id')->sort()->values()->all();

    expect(Pedido::query()->visibleTo($withoutObras)->pluck('id')->sort()->values()->all())->toBe($all);
    expect(Pedido::query()->visibleTo($withObras)->pluck('id')->sort()->values()->all())->toBe($all);
});
