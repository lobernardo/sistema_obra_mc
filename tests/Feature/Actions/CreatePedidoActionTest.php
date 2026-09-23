<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Domain\Pedidos\DataPrevistaCalculator;
use App\Enums\EventTypeSlug;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoCodeGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedWorkflowStatuses();
    EventType::factory()->criacaoPedido()->create();
});

function createPedidoAction(): CreatePedidoAction
{
    return new CreatePedidoAction(new PedidoCodeGenerator);
}

/**
 * @return array{last_value: int|string, is_called: bool}
 */
function createPedidoSequenceState(): array
{
    return (array) DB::selectOne('select last_value, is_called from pedido_code_sequence');
}

/**
 * Asserts the Action refuses `$data` with exactly `$errors`, writing no
 * pedido or event and leaving the code sequence untouched (RF-03, RF-07).
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, array<int, string>>  $errors
 */
function expectCreationRefused(User $requester, array $data, array $errors): void
{
    $pedidoCount = Pedido::query()->count();
    $eventCount = PedidoEvent::query()->count();
    $sequence = createPedidoSequenceState();

    try {
        createPedidoAction()->execute($requester, $data);

        test()->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect($exception->errors())->toBe($errors);
    }

    expect(Pedido::query()->count())->toBe($pedidoCount);
    expect(PedidoEvent::query()->count())->toBe($eventCount);
    expect(createPedidoSequenceState())->toEqual($sequence);
}

/**
 * @return array<string, mixed>
 */
function validCreationInput(array $overrides = []): array
{
    return [
        'needed_at' => '2026-07-01',
        'descricao' => 'Cimento e areia',
        ...$overrides,
    ];
}

dataset('creating papéis', ['obra', 'suprimentos']);

test('valid creation persists the pedido with exactly 1 criacao_pedido event carrying the obra snapshot (RF-08, CT-07)', function (string $role) {
    $requester = User::factory()->{$role}()->create();
    $obra = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $requester->obras()->attach($obra->id);

    $pedido = createPedidoAction()->execute($requester, validCreationInput(['obra_selection' => $obra->id]));

    expect(Pedido::query()->count())->toBe(1);
    expect($pedido->code)->toMatch('/^PED-\d{6}$/');
    expect($pedido->status->slug)->toBe('solicitado');
    expect($pedido->requester_id)->toBe($requester->id);
    expect($pedido->obra_id)->toBe($obra->id);
    expect($pedido->obra_reference)->toBeNull();
    expect($pedido->items_description)->toBe('Cimento e areia');

    expect($pedido->events()->count())->toBe(1);

    $event = $pedido->events()->first();
    expect($event->eventType->slug)->toBe(EventTypeSlug::CriacaoPedido->value);
    expect($event->actor_id)->toBe($requester->id);
    expect($event->new_value)->toBe('Residencial Aurora');
})->with('creating papéis');

test('the initial status is the lowest-sort active status even with every workflow status seeded', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = createPedidoAction()->execute($requester, validCreationInput(['obra_selection' => (string) $obra->id]));

    expect($pedido->status->slug)->toBe('solicitado');
});

test('the 3 RF-03 obra cases are refused on obra_id with the exact message (RF-03)', function (string $role, string $case) {
    $requester = User::factory()->{$role}()->create();
    $ownObra = Obra::factory()->create();
    $requester->obras()->attach($ownObra->id);

    [$obraId, $message] = match ($case) {
        'not associated' => [Obra::factory()->create()->id, 'A obra informada não está associada ao solicitante.'],
        'nonexistent' => [999999, 'A obra informada não está associada ao solicitante.'],
        'concluída' => [tap(Obra::factory()->concluida()->create(), fn (Obra $obra) => $requester->obras()->attach($obra->id))->id, 'A obra informada está inativa e não recebe novas solicitações.'],
    };

    expectCreationRefused($requester, validCreationInput(['obra_selection' => $obraId]), ['obra_id' => [$message]]);
})->with('creating papéis')->with(['not associated', 'nonexistent', 'concluída']);

test('"Outra" stores no obra and the trimmed reference, and the event snapshot names it (RF-04)', function (string $role) {
    $requester = User::factory()->{$role}()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    $pedido = createPedidoAction()->execute($requester, validCreationInput([
        'obra_selection' => 'outra',
        'obra_reference' => '  Galpão provisório  ',
    ]));

    expect($pedido->obra_id)->toBeNull();
    expect($pedido->obra_reference)->toBe('Galpão provisório');
    expect($pedido->events()->first()->new_value)->toBe('Outra — Galpão provisório');
})->with('creating papéis');

test('"Outra" with a blank reference stores it as absent (RF-04)', function (string $reference) {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    $pedido = createPedidoAction()->execute($requester, validCreationInput([
        'obra_selection' => 'outra',
        'obra_reference' => $reference,
    ]));

    expect($pedido->obra_id)->toBeNull();
    expect($pedido->obra_reference)->toBeNull();
    expect($pedido->events()->first()->new_value)->toBe('Outra');
})->with(['empty' => '', 'whitespace' => '   ']);

test('a reference of 256 characters is refused on obra_reference (RF-04)', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    expectCreationRefused($requester, validCreationInput([
        'obra_selection' => 'outra',
        'obra_reference' => Str::repeat('a', 256),
    ]), ['obra_reference' => ['A referência deve ter no máximo 255 caracteres.']]);
});

test('a real obra plus a forged reference persists the obra with no reference (RF-06)', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $pedido = createPedidoAction()->execute($requester, validCreationInput([
        'obra_selection' => $obra->id,
        'obra_reference' => 'X',
    ]));

    expect($pedido->obra_id)->toBe($obra->id);
    expect($pedido->obra_reference)->toBeNull();
});

test('"Outra" named after an existing obra grants nothing on that obra (RF-05)', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);
    $obraX = Obra::factory()->create(['name' => 'Obra X']);
    $pedidoX = Pedido::factory()->create(['obra_id' => $obraX->id, 'status_id' => Status::query()->where('slug', 'solicitado')->value('id')]);
    $obraCount = Obra::query()->count();
    $associationCount = DB::table('obra_profile')->count();

    $pedido = createPedidoAction()->execute($requester, validCreationInput([
        'obra_selection' => 'outra',
        'obra_reference' => 'Obra X',
    ]));

    expect($pedido->obra_id)->toBeNull();
    expect(Obra::query()->count())->toBe($obraCount);
    expect(DB::table('obra_profile')->count())->toBe($associationCount);
    expect($requester->fresh()->can('view', $pedidoX))->toBeFalse();
});

test('a requester with zero active obras is refused even for "Outra", with the papel text (RF-07, F-17)', function (string $role, bool $hasConcluidaObra, string $message) {
    $requester = User::factory()->{$role}()->create();

    if ($hasConcluidaObra) {
        $requester->obras()->attach(Obra::factory()->concluida()->create()->id);
    }

    expectCreationRefused($requester, validCreationInput(['obra_selection' => 'outra']), ['obra_id' => [$message]]);
})->with([
    'obra without associations' => ['obra', false, 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.'],
    'obra with only a Concluído obra' => ['obra', true, 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.'],
    'suprimentos without associations' => ['suprimentos', false, 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou associe-se em Associações.'],
    'suprimentos with only a Concluído obra' => ['suprimentos', true, 'Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou associe-se em Associações.'],
]);

test('noActiveObraMessage is papel-aware (F-17)', function () {
    expect(CreatePedidoAction::noActiveObraMessage(User::factory()->obra()->create()))
        ->toBe('Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.');
    expect(CreatePedidoAction::noActiveObraMessage(User::factory()->suprimentos()->create()))
        ->toBe('Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou associe-se em Associações.');
});

test('missing or invalid Preciso para is refused with the Preciso para messages (RF-09, F-08)', function (?string $neededAt, string $message) {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $data = ['obra_selection' => $obra->id, 'descricao' => 'Cimento e areia'];

    if ($neededAt !== null) {
        $data['needed_at'] = $neededAt;
    }

    expectCreationRefused($requester, $data, ['needed_at' => [$message]]);
    expect($message)->not->toContain('data necessária');
})->with([
    'missing' => [null, 'Informe a data em Preciso para.'],
    'invalid' => ['não-é-data', 'Informe uma data válida em Preciso para.'],
]);

test('missing obra_selection is refused on obra_id', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    expectCreationRefused($requester, validCreationInput(), ['obra_id' => ['Selecione a obra.']]);
});

test('a non-numeric obra_selection is refused on obra_id', function () {
    $requester = User::factory()->obra()->create();
    $requester->obras()->attach(Obra::factory()->create()->id);

    expectCreationRefused($requester, validCreationInput(['obra_selection' => 'abc']), ['obra_id' => ['Obra inválida.']]);
});

test('a missing or blank descrição is refused', function (?string $descricao) {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    $data = ['obra_selection' => $obra->id, 'needed_at' => '2026-07-01'];

    if ($descricao !== null) {
        $data['descricao'] = $descricao;
    }

    expectCreationRefused($requester, $data, ['descricao' => ['Informe a descrição.']]);
})->with(['missing' => null, 'blank' => '   ']);

test('forged server-set fields are ignored (RF-09)', function () {
    $this->travelTo(now()->parse('2026-09-21 12:00:00'));

    $requester = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);
    $otherUser = User::factory()->obra()->create();
    $emAnaliseId = Status::query()->where('slug', 'em_analise')->value('id');

    $pedido = createPedidoAction()->execute($requester, validCreationInput([
        'obra_selection' => $obra->id,
        'requested_at' => '2020-01-01 00:00:00',
        'data_prevista' => '2020-01-05',
        'code' => 'PED-FORJADO',
        'status_id' => $emAnaliseId,
        'requester_id' => $otherUser->id,
    ]))->fresh();

    expect($pedido->requested_at->equalTo(now()))->toBeTrue();
    expect($pedido->data_prevista->toDateString())->toBe(DataPrevistaCalculator::forRequestedAt(now())->toDateString());
    expect($pedido->data_prevista->toDateString())->toBe('2026-09-24');
    expect($pedido->code)->toMatch('/^PED-\d{6}$/');
    expect($pedido->status->slug)->toBe('solicitado');
    expect($pedido->requester_id)->toBe($requester->id);
});

test('a gestao actor is refused with AuthorizationException before consuming a code (RF-01)', function () {
    $requester = User::factory()->gestao()->create();
    $sequence = createPedidoSequenceState();

    expect(fn () => createPedidoAction()->execute($requester, validCreationInput(['obra_selection' => 'outra'])))
        ->toThrow(AuthorizationException::class);

    expect(Pedido::query()->count())->toBe(0);
    expect(createPedidoSequenceState())->toEqual($sequence);
});

test('a failure inserting the criacao_pedido event rolls back the pedido insert too (RF-08)', function () {
    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    DB::connection()->beforeExecuting(function (string $query): void {
        if (str_starts_with($query, 'insert into "pedido_events"')) {
            throw new QueryException(DB::getDefaultConnection(), $query, [], new RuntimeException('Falha simulada.'));
        }
    });

    expect(fn () => createPedidoAction()->execute($requester, validCreationInput(['obra_selection' => $obra->id])))
        ->toThrow(QueryException::class);

    expect(Pedido::query()->count())->toBe(0);
    expect(PedidoEvent::query()->count())->toBe(0);
});
