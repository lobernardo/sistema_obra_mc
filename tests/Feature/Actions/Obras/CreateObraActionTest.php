<?php

use App\Actions\Obras\CreateObraAction;
use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = app(CreateObraAction::class);
});

/**
 * Runs `$callback` and returns the PT-BR errors of the ValidationException
 * it must throw.
 *
 * @return array<string, list<string>>
 */
function obraValidationErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('A ValidationException was expected.');
}

test('gestao and suprimentos create an obra with one audit record naming the actor (RF-01)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();

    $obra = $this->action->execute($actor, [
        'name' => 'Residencial Aurora',
        'responsavel' => 'Eng. Carla',
        'status' => 'a_iniciar',
    ]);

    expect(Obra::query()->count())->toBe(1);

    $obra = $obra->fresh();

    expect($obra->name)->toBe('Residencial Aurora');
    expect($obra->responsavel)->toBe('Eng. Carla');
    expect($obra->status)->toBe(ObraStatus::AIniciar);
    expect($obra->is_demo)->toBeFalse();

    $events = ObraAdminEvent::query()->get();

    expect($events)->toHaveCount(1);
    expect($events[0]->action)->toBe(ObraAdminAction::ObraCreated);
    expect($events[0]->actor_id)->toBe($actor->id);
    expect($events[0]->obra_id)->toBe($obra->id);
    expect($events[0]->before)->toBeNull();
    expect($events[0]->after)->toBe([
        'name' => 'Residencial Aurora',
        'responsavel' => 'Eng. Carla',
        'status' => 'a_iniciar',
    ]);
})->with(['gestao', 'suprimentos']);

test('the name is trimmed and an empty responsavel is stored as null', function () {
    $obra = $this->action->execute(User::factory()->gestao()->create(), [
        'name' => '   Comercial Bravo  ',
        'responsavel' => '   ',
        'status' => ObraStatus::EmAndamento,
    ]);

    expect($obra->fresh()->name)->toBe('Comercial Bravo');
    expect($obra->fresh()->responsavel)->toBeNull();
});

test('invalid input is rejected with a PT-BR 422 and writes nothing', function (array $data, string $field, string $message) {
    $actor = User::factory()->gestao()->create();

    $errors = obraValidationErrors(fn () => $this->action->execute($actor, $data));

    expect($errors)->toHaveKey($field);
    expect($errors[$field][0])->toBe($message);
    expect(Obra::query()->count())->toBe(0);
    expect(ObraAdminEvent::query()->count())->toBe(0);
})->with([
    'empty name' => [['name' => '', 'status' => 'a_iniciar'], 'name', 'Informe o nome da obra.'],
    'whitespace-only name' => [['name' => '   ', 'status' => 'a_iniciar'], 'name', 'Informe o nome da obra.'],
    'name over 255' => [['name' => str_repeat('a', 256), 'status' => 'a_iniciar'], 'name', 'O nome deve ter no máximo 255 caracteres.'],
    'responsavel over 255' => [['name' => 'Obra X', 'responsavel' => str_repeat('r', 256), 'status' => 'a_iniciar'], 'responsavel', 'O responsável deve ter no máximo 255 caracteres.'],
    'status outside the 3 values' => [['name' => 'Obra X', 'status' => 'pausada'], 'status', 'Status inválido.'],
    'missing status' => [['name' => 'Obra X'], 'status', 'Selecione o status da obra.'],
]);

test('a name equal to an existing one after trim and case folding is rejected (Q-03)', function () {
    $actor = User::factory()->gestao()->create();

    $this->action->execute($actor, ['name' => 'Obra Centro', 'status' => 'em_andamento']);

    $errors = obraValidationErrors(fn () => $this->action->execute($actor, [
        'name' => '  obra centro ',
        'status' => 'a_iniciar',
    ]));

    expect($errors['name'])->toBe(['Já existe uma obra com este nome.']);
    expect(Obra::query()->count())->toBe(1);
    expect(ObraAdminEvent::query()->count())->toBe(1);
});

/**
 * RF-01 race: the pre-check passes, then a concurrent request commits the
 * same normalized name before our INSERT reaches the index. The rival row
 * is written on a second connection (autocommit), so it survives the
 * savepoint rollback of the Action exactly like a committed concurrent
 * request would; the hook fires right before our INSERT.
 */
test('a concurrent create reaching the unique index surfaces as ValidationException, never 500', function () {
    $actor = User::factory()->suprimentos()->create();

    config(['database.connections.pgsql_race' => config('database.connections.'.config('database.default'))]);
    $rival = DB::connection('pgsql_race');
    $injected = false;

    DB::connection()->beforeExecuting(function (string $query) use (&$injected, $rival): void {
        if ($injected || ! str_starts_with($query, 'insert into "obras"')) {
            return;
        }

        $injected = true;

        $rival->table('obras')->insert([
            'name' => 'Obra Centro',
            'status' => 'em_andamento',
            'is_demo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    try {
        $errors = obraValidationErrors(fn () => $this->action->execute($actor, [
            'name' => '  obra centro ',
            'status' => 'a_iniciar',
        ]));

        expect($injected)->toBeTrue();
        expect($errors['name'])->toBe(['Já existe uma obra com este nome.']);
        expect(Obra::query()->count())->toBe(1);
        expect(Obra::query()->value('name'))->toBe('Obra Centro');
        expect(ObraAdminEvent::query()->count())->toBe(0);
    } finally {
        $rival->table('obras')->where('name', 'Obra Centro')->delete();
        DB::purge('pgsql_race');
    }
});

test('is_demo is never taken from input', function () {
    $obra = $this->action->execute(User::factory()->gestao()->create(), [
        'name' => 'Obra Real',
        'status' => 'em_andamento',
        'is_demo' => true,
    ]);

    expect($obra->fresh()->is_demo)->toBeFalse();
});

test('an obra actor gets AuthorizationException and nothing is written (RF-07)', function () {
    $actor = User::factory()->obra()->create();

    expect(fn () => $this->action->execute($actor, ['name' => 'Obra Proibida', 'status' => 'a_iniciar']))
        ->toThrow(AuthorizationException::class, 'Apenas os perfis Gestão e Suprimentos podem administrar obras.');

    expect(Obra::query()->count())->toBe(0);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});
