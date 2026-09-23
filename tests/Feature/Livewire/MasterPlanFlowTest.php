<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\EventTypeSlug;
use App\Enums\RoleSlug;
use App\Livewire\Associacoes\Index as AssociacoesIndex;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\Register;
use App\Livewire\Pedidos\NovaSolicitacao;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Master plan §45 / RF-25 (navegacao-sidebar-listagens T23, F-16): the flow
 * that crosses the three slices, end to end, as a Feature test.
 *
 * Novo Cadastro (`register` → `Auth\Register`) → empty Nova Solicitação
 * (`CreatePedidoAction::noActiveObraMessage()`) and a forged creation that
 * fails on `obra_id` → association by Suprimentos or Gestão
 * (`Associacoes\Index::attach()` → `AttachUserObrasAction`) → login
 * (`Auth\LoginForm`) → `/home` lands on `obra.pedidos.index` → "+ Nova
 * Solicitação" from the sidebar (`obra.nova-solicitacao` →
 * `Pedidos\NovaSolicitacao::submit()`) → the pedido in the Acompanhamento
 * as "Solicitante / Obra".
 *
 * Factories only create the pre-existing actors, obra A and the lookups;
 * every step of the new user goes through the real routes, components and
 * Actions (upstream names checked by gate G-2).
 */
const MASTER_PLAN_PASSWORD = 'senha-forte-123';

dataset('associating papéis', [
    'Suprimentos' => [RoleSlug::Suprimentos],
    'Gestão' => [RoleSlug::Gestao],
]);

/**
 * `last_value`/`is_called` of the pedido code sequence.
 *
 * @return array<string, mixed>
 */
function masterPlanCodeSequence(): array
{
    return (array) DB::selectOne('select last_value, is_called from pedido_code_sequence');
}

test('Novo Cadastro → associação → pedido na obra associada (§45, RF-25)', function (RoleSlug $associatingRole) {
    seedWorkflowStatuses();
    seedHistoryEventTypes();
    Role::factory()->obra()->create();

    $associators = [
        RoleSlug::Suprimentos->value => User::factory()->suprimentos()->create(),
        RoleSlug::Gestao->value => User::factory()->gestao()->create(),
    ];
    $obraA = Obra::factory()->emAndamento()->create(['name' => 'Residencial Aurora']);
    $neededAt = now()->addDays(10)->toDateString();

    // 1. Novo Cadastro.
    $this->get(route('register'))->assertOk();

    Livewire::test(Register::class)
        ->set('name', 'Ana Obra')
        ->set('email', 'ana.obra@example.com')
        ->set('password', MASTER_PLAN_PASSWORD)
        ->set('password_confirmation', MASTER_PLAN_PASSWORD)
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $newUser = User::query()->where('email', 'ana.obra@example.com')->sole();

    expect(User::query()->count())->toBe(3);
    expect($newUser->role->slug)->toBe(RoleSlug::Obra->value);
    expect(DB::table('obra_profile')->where('user_id', $newUser->id)->count())->toBe(0);

    // 2. Empty state, and a forged creation for obra A that records nothing.
    $this->actingAs($newUser);

    $html = $this->get(route('obra.nova-solicitacao'))
        ->assertOk()
        ->assertSee(CreatePedidoAction::noActiveObraMessage($newUser))
        ->getContent();

    expect(preg_match('/<main\b.*?<\/main>/s', $html, $main))->toBe(1);
    expect($main[0])->not->toContain('<form');

    $sequenceBefore = masterPlanCodeSequence();

    Livewire::test(NovaSolicitacao::class)
        ->set('obra_selection', (string) $obraA->id)
        ->set('descricao', 'Cimento CP-II 50 sacos')
        ->set('needed_at', $neededAt)
        ->call('submit')
        ->assertHasErrors('obra_id');

    expect(Pedido::query()->count())->toBe(0);
    expect(masterPlanCodeSequence())->toBe($sequenceBefore);

    // 3. Association by the dataset papel.
    $this->actingAs($associators[$associatingRole->value]);

    Livewire::test(AssociacoesIndex::class)
        ->set("selectedObraIds.{$newUser->id}", [$obraA->id])
        ->call('attach', $newUser->id)
        ->assertHasNoErrors();

    expect(DB::table('obra_profile')->get(['obra_id', 'user_id'])->map(fn (object $row): array => (array) $row)->all())
        ->toBe([['obra_id' => $obraA->id, 'user_id' => $newUser->id]]);

    // 4. Logout, login, landing and "+ Nova Solicitação" from the sidebar.
    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();

    Livewire::test(LoginForm::class)
        ->set('email', 'ana.obra@example.com')
        ->set('password', MASTER_PLAN_PASSWORD)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($newUser);

    $this->get('/home')->assertRedirect('/obra/pedidos');

    $listing = $this->get(route('obra.pedidos.index'))->assertOk()->getContent();

    expect(preg_match('/<a\b[^>]*data-testid="sidebar-nova-solicitacao"[^>]*>/', $listing, $sidebarLink))->toBe(1);
    expect(preg_match('/href="([^"]+)"/', $sidebarLink[0], $href))->toBe(1);
    expect(html_entity_decode($href[1]))->toBe(route('obra.nova-solicitacao'));

    $form = $this->get(html_entity_decode($href[1]))->assertOk()->getContent();

    expect(preg_match('/<main\b.*?<\/main>/s', $form, $formMain))->toBe(1);
    expect($formMain[0])->toContain('<form')->toContain('wire:submit');

    $code = Livewire::test(NovaSolicitacao::class)
        ->set('obra_selection', (string) $obraA->id)
        ->set('descricao', 'Cimento CP-II 50 sacos')
        ->set('needed_at', $neededAt)
        ->call('submit')
        ->assertHasNoErrors()
        ->get('code');

    expect($code)->not->toBeNull();

    // 5. Acompanhamento.
    $this->get(route('obra.pedidos.index'))
        ->assertOk()
        ->assertSee($code)
        ->assertSee('Ana Obra / Residencial Aurora');

    $pedido = Pedido::query()->sole();

    expect($pedido->code)->toBe($code);
    expect($pedido->requester_id)->toBe($newUser->id);
    expect($pedido->obra_id)->toBe($obraA->id);

    $creationEvents = PedidoEvent::query()
        ->where('pedido_id', $pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::CriacaoPedido->value))
        ->get();

    expect($creationEvents)->toHaveCount(1);
    expect($creationEvents->first()->actor_id)->toBe($newUser->id);
})->with('associating papéis');
