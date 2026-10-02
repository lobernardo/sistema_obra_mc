<?php

use App\Actions\Obras\AcceptObraInvitationAction;
use App\Actions\Obras\DeleteObraAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Pedidos\CreatePedidoAction;
use App\Exceptions\ObraInvitations\ObraInvitationUnavailableException;
use App\Livewire\Auth\ObraInvitationPage;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RNF-01 / CT-05 (`obras-ativacao-exclusao`): a write that races an obra
 * deletion ends in a PT-BR 422, never an HTTP 500. Each case lets the
 * Action pass its pre-checks and then removes the obra right before the
 * referencing INSERT — so the real FK constraint fires — or injects a
 * deadlock (SQLSTATE 40P01) at that same point.
 */
function obraGoneRaceDeadlock(): QueryException
{
    $pdoException = new PDOException('SQLSTATE[40P01]: Deadlock detected');
    $pdoException->errorInfo = ['40P01', 7, 'ERROR: deadlock detected'];

    return new QueryException('pgsql', 'insert into "pedidos"', [], $pdoException);
}

/**
 * @return array<string, list<string>>
 */
function obraGoneRaceErrors(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);

        return $exception->errors();
    }

    throw new RuntimeException('A ValidationException was expected.');
}

/**
 * Removes the obra the way a concurrent `DeleteObraAction` would have: its
 * associations cascade, nothing else references it.
 */
function obraGoneRaceDelete(Obra $obra): void
{
    DB::table('obras')->where('id', $obra->id)->delete();
}

describe('pedido creation', function () {
    beforeEach(function () {
        seedWorkflowStatuses();
        seedHistoryEventTypes();
        Storage::fake(PedidoAttachmentStorage::DISK);

        $this->requester = User::factory()->obra()->create();
        $this->obra = Obra::factory()->create(['name' => 'Obra Corrida']);
        $this->requester->obras()->attach($this->obra->id);

        $this->payload = [
            'obra_selection' => (string) $this->obra->id,
            'descricao' => 'Cimento e areia',
            'needed_at' => '2026-10-20',
            'anexos' => [UploadedFile::fake()->createWithContent('orcamento.pdf', anexoPdfBytes())],
        ];
    });

    afterEach(function () {
        expect(Pedido::query()->count())->toBe(0);
        expect(PedidoEvent::query()->count())->toBe(0);
        expect(PedidoAttachment::query()->count())->toBe(0);
        expect(InternalNotification::query()->count())->toBe(0);
        expect(Storage::disk(PedidoAttachmentStorage::DISK)->allFiles())->toBe([]);
    });

    test('an obra deleted after the pre-checks yields 422 obra_id with nothing written (RNF-01)', function () {
        Pedido::creating(fn () => obraGoneRaceDelete($this->obra));

        expect(obraGoneRaceErrors(fn () => app(CreatePedidoAction::class)->execute($this->requester, $this->payload)))
            ->toBe(['obra_id' => ['A obra informada não foi encontrada.']]);
    });

    test('a deadlock on the pedido insert yields the same 422 (RNF-01)', function () {
        Pedido::creating(function (): void {
            throw obraGoneRaceDeadlock();
        });

        expect(obraGoneRaceErrors(fn () => app(CreatePedidoAction::class)->execute($this->requester, $this->payload)))
            ->toBe(['obra_id' => ['A obra informada não foi encontrada.']]);
    });

    test('a failure after a file is stored removes the file and still yields the 422 (RNF-01, RNF-02)', function () {
        PedidoAttachment::creating(function (): void {
            throw obraGoneRaceDeadlock();
        });

        expect(obraGoneRaceErrors(fn () => app(CreatePedidoAction::class)->execute($this->requester, $this->payload)))
            ->toBe(['obra_id' => ['A obra informada não foi encontrada.']]);
    });
});

describe('convite generation', function () {
    beforeEach(function () {
        $this->actor = User::factory()->gestao()->create();
        $this->obra = Obra::factory()->create();
    });

    afterEach(function () {
        expect(ObraInvitation::query()->count())->toBe(0);
        expect(ObraAdminEvent::query()->count())->toBe(0);
    });

    test('an obra deleted after the activity check yields 422 obra with no convite nor audit (RNF-01)', function () {
        ObraInvitation::creating(fn () => obraGoneRaceDelete($this->obra));

        expect(obraGoneRaceErrors(fn () => app(GenerateObraInvitationAction::class)->execute($this->actor, $this->obra)))
            ->toBe(['obra' => ['A obra informada não foi encontrada.']]);
    });

    test('a deadlock on the convite insert yields the same 422 (RNF-01)', function () {
        ObraInvitation::creating(function (): void {
            throw obraGoneRaceDeadlock();
        });

        expect(obraGoneRaceErrors(fn () => app(GenerateObraInvitationAction::class)->execute($this->actor, $this->obra)))
            ->toBe(['obra' => ['A obra informada não foi encontrada.']]);
    });
});

test('an unrelated database error during pedido creation is not masked as obra não encontrada (RNF-01)', function () {
    seedWorkflowStatuses();
    EventType::factory()->criacaoPedido()->create();

    $requester = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $requester->obras()->attach($obra->id);

    Pedido::creating(function (): void {
        $pdoException = new PDOException('SQLSTATE[23503]: Foreign key violation');
        $pdoException->errorInfo = ['23503', 7, 'ERROR: violates foreign key constraint "pedidos_status_id_foreign"'];

        throw new QueryException('pgsql', 'insert into "pedidos"', [], $pdoException);
    });

    expect(fn () => app(CreatePedidoAction::class)->execute($requester, [
        'obra_selection' => (string) $obra->id,
        'descricao' => 'Areia',
        'needed_at' => '2026-10-20',
    ]))->toThrow(QueryException::class);
});

test('a convite deleted with its obra ends the acceptance in /convite/indisponivel (RNF-01, Q-03)', function () {
    $gestao = User::factory()->gestao()->create();
    $obra = Obra::factory()->create();
    $result = app(GenerateObraInvitationAction::class)->execute($gestao, $obra);
    $token = parse_url($result['url'], PHP_URL_FRAGMENT);
    $invitationId = $result['invitation']->id;
    $user = User::factory()->obra()->create();

    $page = Livewire::actingAs($user)->test(ObraInvitationPage::class)
        ->call('lookup', $token)
        ->assertSet('invitationId', $invitationId);

    app(DeleteObraAction::class)->execute($gestao, $obra);

    expect(ObraInvitation::query()->whereKey($invitationId)->exists())->toBeFalse();

    expect(fn () => app(AcceptObraInvitationAction::class)->acceptAsExistingAccount($user, $invitationId))
        ->toThrow(ObraInvitationUnavailableException::class);

    $page->call('confirm')->assertRedirect(route('obra-invitation.unavailable'));

    expect(DB::table('obra_profile')->where('user_id', $user->id)->count())->toBe(0);
});
