<?php

use App\Actions\Obras\UpdateObraAction;
use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = app(UpdateObraAction::class);
});

/**
 * @return array<string, list<string>>
 */
function obraUpdateValidationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('A ValidationException was expected.');
}

test('Em andamento → Concluído writes one audit with only the status before and after (RF-02)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Norte', 'responsavel' => 'Eng. Davi']);

    $this->action->execute($actor, $obra, [
        'name' => 'Obra Norte',
        'responsavel' => 'Eng. Davi',
        'status' => 'concluido',
    ]);

    expect($obra->fresh()->status)->toBe(ObraStatus::Concluido);

    $events = ObraAdminEvent::query()->get();

    expect($events)->toHaveCount(1);
    expect($events[0]->action)->toBe(ObraAdminAction::ObraUpdated);
    expect($events[0]->actor_id)->toBe($actor->id);
    expect($events[0]->obra_id)->toBe($obra->id);
    expect($events[0]->before)->toBe(['status' => 'em_andamento']);
    expect($events[0]->after)->toBe(['status' => 'concluido']);
})->with(['gestao', 'suprimentos']);

test('an identical resubmission writes no audit and does not touch the row', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Sul', 'responsavel' => null]);
    $updatedAt = $obra->fresh()->updated_at;

    $this->travel(5)->minutes();

    $this->action->execute($actor, $obra, [
        'name' => '  Obra Sul ',
        'responsavel' => '',
        'status' => 'em_andamento',
    ]);

    expect(ObraAdminEvent::query()->count())->toBe(0);
    expect($obra->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

test('every changed key — and only those — is audited', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->aIniciar()->create(['name' => 'Obra Leste', 'responsavel' => null]);

    $this->action->execute($actor, $obra, [
        'name' => 'Obra Leste II',
        'responsavel' => 'Eng. Bia',
        'status' => 'a_iniciar',
    ]);

    $event = ObraAdminEvent::query()->sole();

    expect($event->before)->toBe(['name' => 'Obra Leste', 'responsavel' => null]);
    expect($event->after)->toBe(['name' => 'Obra Leste II', 'responsavel' => 'Eng. Bia']);
});

test('a Concluído obra may go back to A iniciar or Em andamento', function (string $target) {
    $obra = Obra::factory()->concluida()->create();

    $this->action->execute(User::factory()->suprimentos()->create(), $obra, [
        'name' => $obra->name,
        'status' => $target,
    ]);

    expect($obra->fresh()->status->value)->toBe($target);
})->with(['a_iniciar', 'em_andamento']);

test('renaming B to the name of A is rejected and B stays unchanged', function () {
    $actor = User::factory()->gestao()->create();
    Obra::factory()->create(['name' => 'Obra Centro']);
    $obraB = Obra::factory()->aIniciar()->create(['name' => 'Obra Bairro']);

    $errors = obraUpdateValidationErrors(fn () => $this->action->execute($actor, $obraB, [
        'name' => ' OBRA CENTRO',
        'status' => 'em_andamento',
    ]));

    expect($errors['name'])->toBe(['Já existe uma obra com este nome.']);
    expect($obraB->fresh()->name)->toBe('Obra Bairro');
    expect($obraB->fresh()->status)->toBe(ObraStatus::AIniciar);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});

test('an obra may keep its own name with different casing', function () {
    $obra = Obra::factory()->create(['name' => 'obra centro']);

    $this->action->execute(User::factory()->gestao()->create(), $obra, [
        'name' => 'Obra Centro',
        'status' => $obra->status->value,
    ]);

    expect($obra->fresh()->name)->toBe('Obra Centro');
});

test('invalid input is rejected with a PT-BR 422 and the obra is unchanged', function (array $data, string $field) {
    $obra = Obra::factory()->create(['name' => 'Obra Oeste']);

    $errors = obraUpdateValidationErrors(fn () => $this->action->execute(User::factory()->gestao()->create(), $obra, $data));

    expect($errors)->toHaveKey($field);
    expect($obra->fresh()->name)->toBe('Obra Oeste');
    expect(ObraAdminEvent::query()->count())->toBe(0);
})->with([
    'empty name' => [['name' => '', 'status' => 'a_iniciar'], 'name'],
    'responsavel over 255' => [['name' => 'Obra Oeste', 'responsavel' => str_repeat('r', 256), 'status' => 'a_iniciar'], 'responsavel'],
    'status outside the 3 values' => [['name' => 'Obra Oeste', 'status' => 'ativa'], 'status'],
]);

test('a concurrent rename reaching the unique index surfaces as ValidationException, never 500', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->create(['name' => 'Obra Antiga']);

    config(['database.connections.pgsql_race' => config('database.connections.'.config('database.default'))]);
    $rival = DB::connection('pgsql_race');
    $injected = false;

    DB::connection()->beforeExecuting(function (string $query) use (&$injected, $rival): void {
        if ($injected || ! str_starts_with($query, 'update "obras"')) {
            return;
        }

        $injected = true;

        $rival->table('obras')->insert([
            'name' => 'Obra Nova',
            'status' => 'em_andamento',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    try {
        $errors = obraUpdateValidationErrors(fn () => $this->action->execute($actor, $obra, [
            'name' => 'obra nova',
            'status' => 'em_andamento',
        ]));

        expect($injected)->toBeTrue();
        expect($errors['name'])->toBe(['Já existe uma obra com este nome.']);
        expect($obra->name)->toBe('Obra Antiga');
        expect($obra->fresh()->name)->toBe('Obra Antiga');
        expect(ObraAdminEvent::query()->count())->toBe(0);
    } finally {
        $rival->table('obras')->where('name', 'Obra Nova')->delete();
        DB::purge('pgsql_race');
    }
});

test('editing to Concluído keeps every pedido, pedido_event and obra_profile row of the obra (RF-02)', function () {
    $obra = Obra::factory()->emAndamento()->create();
    $first = Pedido::factory()->create(['obra_id' => $obra->id]);
    $pedidos = collect([$first, Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => $first->status_id])]);
    PedidoEvent::factory()->create(['pedido_id' => $first->id]);
    $obra->users()->attach(User::factory()->obra()->create());

    $counts = fn (): array => [
        Pedido::query()->where('obra_id', $obra->id)->count(),
        PedidoEvent::query()->whereIn('pedido_id', $pedidos->pluck('id'))->count(),
        DB::table('obra_profile')->where('obra_id', $obra->id)->count(),
    ];

    $before = $counts();

    $this->action->execute(User::factory()->gestao()->create(), $obra, [
        'name' => $obra->name,
        'status' => 'concluido',
    ]);

    expect($counts())->toBe($before);
    expect($before)->toBe([2, 1, 3]);
});

test('an obra actor gets AuthorizationException and the obra is unchanged (RF-07)', function () {
    $obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Fixa']);

    expect(fn () => $this->action->execute(User::factory()->obra()->create(), $obra, [
        'name' => 'Obra Alterada',
        'status' => 'concluido',
    ]))->toThrow(AuthorizationException::class);

    expect($obra->fresh()->name)->toBe('Obra Fixa');
    expect($obra->fresh()->status)->toBe(ObraStatus::EmAndamento);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});
