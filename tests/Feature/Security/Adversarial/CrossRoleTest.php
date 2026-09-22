<?php

use App\Actions\Pedidos\CancelPedidoAction;
use App\Actions\Pedidos\UpdatePedidoPrevisaoAction;
use App\Actions\Pedidos\UpdatePedidoPrioridadeAction;
use App\Actions\Pedidos\UpdatePedidoResponsavelAction;
use App\Actions\Pedidos\UpdatePedidoStatusAction;
use App\Actions\Usuarios\CreateUserAction;
use App\Actions\Usuarios\SendAccessLinkAction;
use App\Actions\Usuarios\SetUserActiveAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\RoleSlug;
use App\Livewire\Kanban\KanbanBoard;
use App\Livewire\Suprimentos\PedidoDetalhe as SuprimentosPedidoDetalhe;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Priority;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Routing\Route as RegisteredRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * RF-30 (G-05..G-08), AC-F03..AC-F08, AC-F10 — adversarial cross-role suite
 * (decision D-12). Each papel attacks the surface reserved to another papel
 * through every backend entry point: prefixed routes enumerated from the
 * router (so a route added later is covered automatically), Livewire
 * handlers and the Actions called directly. Assertions are HTTP status,
 * exception class or database state — never the rendered navigation.
 */

/**
 * Every registered route whose name starts with the given prefix, with the
 * `{pedido}`/`{user}` parameters resolved to real ids so the gate — not a
 * 404 from route-model binding — is what answers.
 *
 * @return array<string, string> route name => resolved URL
 */
function adversarialRoutesNamed(string $prefix, Pedido $pedido, User $user): array
{
    $urls = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        /** @var RegisteredRoute $route */
        $name = $route->getName();

        if ($name === null || ! str_starts_with($name, $prefix)) {
            continue;
        }

        $urls[$name] = route($name, ['pedido' => $pedido->id, 'user' => $user->id]);
    }

    ksort($urls);

    return $urls;
}

/**
 * @return array{status_id: int, priority_id: int|null, responsible_id: int|null, expected_delivery_at: string|null, events: int}
 */
function adversarialPedidoState(Pedido $pedido): array
{
    $fresh = $pedido->fresh();

    return [
        'status_id' => $fresh->status_id,
        'priority_id' => $fresh->priority_id,
        'responsible_id' => $fresh->responsible_id,
        'expected_delivery_at' => $fresh->expected_delivery_at?->toDateString(),
        'events' => PedidoEvent::query()->where('pedido_id', $pedido->id)->count(),
    ];
}

describe('G-05 / G-06 — obra papel against the suprimentos and gestao areas', function () {
    beforeEach(function () {
        $this->obra = User::factory()->obra()->create();
        $this->pedido = Pedido::factory()->create([
            'requester_id' => $this->obra->id,
            'status_id' => Status::factory()->solicitado()->create()->id,
        ]);
        $this->otherUser = User::factory()->suprimentos()->create();
    });

    test('G-05 obra receives 403 on every route named suprimentos.* (AC-F03, AC-F07)', function () {
        $routes = adversarialRoutesNamed('suprimentos.', $this->pedido, $this->otherUser);

        expect(array_keys($routes))->toBe([
            'suprimentos.kanban',
            'suprimentos.pedidos.index',
            'suprimentos.pedidos.show',
            'suprimentos.visao-geral',
        ]);

        foreach ($routes as $name => $url) {
            $this->actingAs($this->obra)->get($url)->assertForbidden();
        }

        $this->actingAs($this->obra)->get(route('obra.pedidos.show', $this->pedido))->assertOk();
    });

    test('G-06 obra receives 403 on every route named gestao.* (AC-F04, AC-F07)', function () {
        $routes = adversarialRoutesNamed('gestao.', $this->pedido, $this->otherUser);

        expect(array_keys($routes))->toBe([
            'gestao.dashboard',
            'gestao.kanban',
            'gestao.pedidos.index',
            'gestao.pedidos.show',
            'gestao.usuarios.create',
            'gestao.usuarios.edit',
            'gestao.usuarios.index',
        ]);

        foreach ($routes as $name => $url) {
            $this->actingAs($this->obra)->get($url)->assertForbidden();
        }

        expect(UserAdminEvent::query()->count())->toBe(0);
    });
});

describe('G-07 — suprimentos papel against user administration', function () {
    beforeEach(function () {
        Notification::fake();

        $this->suprimentos = User::factory()->suprimentos()->create();
        $this->target = User::factory()->obra()->create();
        $this->target->obras()->attach(Obra::factory()->create()->id);
        $this->obraRole = Role::query()->where('slug', RoleSlug::Obra->value)->firstOrFail();
        $this->gestaoRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Gestao->value], ['name' => 'Gestão']);
    });

    test('G-07 suprimentos receives 403 on /gestao/usuarios, /gestao/usuarios/novo and /gestao/usuarios/{id}/editar (AC-F05, AC-F07)', function () {
        $this->actingAs($this->suprimentos)->get(route('gestao.usuarios.index'))->assertForbidden();
        $this->actingAs($this->suprimentos)->get(route('gestao.usuarios.create'))->assertForbidden();
        $this->actingAs($this->suprimentos)->get(route('gestao.usuarios.edit', $this->target))->assertForbidden();

        $this->actingAs($this->suprimentos)->get('/gestao/usuarios')->assertForbidden();
        $this->actingAs($this->suprimentos)->get('/gestao/usuarios/novo')->assertForbidden();
        $this->actingAs($this->suprimentos)->get('/gestao/usuarios/'.$this->target->id.'/editar')->assertForbidden();
    });

    test('G-07 each of the 4 Actions/Usuarios called directly by suprimentos throws AuthorizationException and leaves the database untouched (AC-F05, AC-F10)', function () {
        $usersBefore = User::query()->count();
        $obraProfileBefore = DB::table('obra_profile')->count();
        $targetBefore = $this->target->fresh()->only(['name', 'email', 'role_id', 'is_active']);

        expect(fn () => app(CreateUserAction::class)->execute($this->suprimentos, [
            'name' => 'Forjado',
            'email' => 'forjado@example.com',
            'role_id' => $this->gestaoRole->id,
        ]))->toThrow(AuthorizationException::class);

        expect(fn () => app(UpdateUserAction::class)->execute($this->suprimentos, $this->target, [
            'name' => 'Renomeado',
            'email' => $this->target->email,
            'role_id' => $this->gestaoRole->id,
        ]))->toThrow(AuthorizationException::class);

        expect(fn () => app(SetUserActiveAction::class)->execute($this->suprimentos, $this->target, false))
            ->toThrow(AuthorizationException::class);

        expect(fn () => app(SendAccessLinkAction::class)->execute($this->suprimentos, $this->target))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(SendAccessLinkAction::class)->execute($this->suprimentos, $this->target, resend: true))
            ->toThrow(AuthorizationException::class);

        expect(User::query()->count())->toBe($usersBefore);
        expect(User::query()->where('email', 'forjado@example.com')->exists())->toBeFalse();
        expect(DB::table('obra_profile')->count())->toBe($obraProfileBefore);
        expect($this->target->fresh()->only(['name', 'email', 'role_id', 'is_active']))->toBe($targetBefore);
        expect(DB::table('password_reset_tokens')->where('email', $this->target->email)->exists())->toBeFalse();
        expect(UserAdminEvent::query()->count())->toBe(0);
        Notification::assertNothingSent();
    });
});

describe('G-08 — gestao papel against the operational mutations of suprimentos', function () {
    beforeEach(function () {
        $this->gestao = User::factory()->gestao()->create();
        $this->suprimentos = User::factory()->suprimentos()->create();
        $this->solicitado = Status::factory()->solicitado()->create();
        $this->emAnalise = Status::factory()->emAnalise()->create();
        $this->priority = Priority::factory()->alta()->create();
        $this->pedido = Pedido::factory()->create(['status_id' => $this->solicitado->id]);
        $this->stateBefore = adversarialPedidoState($this->pedido);
    });

    test('G-08 each of the 5 operational Actions called directly by gestao throws AuthorizationException and changes nothing (AC-F06, AC-F10)', function () {
        $pedido = $this->pedido;

        expect(fn () => app(UpdatePedidoStatusAction::class)->execute($this->gestao, $pedido, $this->emAnalise->id))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(UpdatePedidoResponsavelAction::class)->execute($this->gestao, $pedido, $this->suprimentos->id))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(UpdatePedidoPrioridadeAction::class)->execute($this->gestao, $pedido, $this->priority->id))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(UpdatePedidoPrevisaoAction::class)->execute($this->gestao, $pedido, now()->addDays(3)->toDateString()))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(CancelPedidoAction::class)->execute($this->gestao, $pedido))
            ->toThrow(AuthorizationException::class);

        expect(adversarialPedidoState($pedido))->toBe($this->stateBefore);
    });

    test('G-08 gestao mounting Suprimentos\PedidoDetalhe to reach any of the 5 handlers throws AuthorizationException at mount (AC-F06, AC-F08)', function (string $handler) {
        $this->actingAs($this->gestao);
        $this->withoutExceptionHandling();

        expect(fn () => Livewire::test(SuprimentosPedidoDetalhe::class, ['pedido' => $this->pedido])->call($handler))
            ->toThrow(AuthorizationException::class);

        expect(adversarialPedidoState($this->pedido))->toBe($this->stateBefore);
    })->with(['updateStatus', 'updateResponsavel', 'updatePrioridade', 'updatePrevisao', 'cancelarPedido']);

    test('G-08 gestao invoking a Suprimentos\PedidoDetalhe handler of a component mounted by suprimentos is refused by the policy check (AC-F06, AC-F08)', function (string $handler) {
        $mountAsSuprimentos = function () {
            $this->actingAs($this->suprimentos);

            $component = Livewire::test(SuprimentosPedidoDetalhe::class, ['pedido' => $this->pedido])
                ->set('status_id', $this->emAnalise->id)
                ->set('responsible_id', $this->suprimentos->id)
                ->set('priority_id', $this->priority->id)
                ->set('expected_delivery_at', now()->addDays(3)->toDateString());

            $this->actingAs($this->gestao);

            return $component;
        };

        $mountAsSuprimentos()->call($handler)->assertForbidden();

        $this->withoutExceptionHandling();

        $component = $mountAsSuprimentos();

        expect(fn () => $component->call($handler))->toThrow(AuthorizationException::class);

        expect(adversarialPedidoState($this->pedido))->toBe($this->stateBefore);
    })->with(['updateStatus', 'updateResponsavel', 'updatePrioridade', 'updatePrevisao', 'cancelarPedido']);

    test('G-08 gestao calling KanbanBoard::moveCard and moveViaControl throws AuthorizationException and the card stays put (AC-F06, AC-F08)', function () {
        $this->actingAs($this->gestao);
        $this->withoutExceptionHandling();

        expect(fn () => Livewire::test(KanbanBoard::class)->call('moveCard', $this->pedido->id, 0, $this->emAnalise->id))
            ->toThrow(AuthorizationException::class);
        expect(fn () => Livewire::test(KanbanBoard::class)->call('moveViaControl', $this->pedido->id, $this->emAnalise->id))
            ->toThrow(AuthorizationException::class);

        $boardOpenedBySuprimentos = function () {
            $this->actingAs($this->suprimentos);
            $board = Livewire::test(KanbanBoard::class)->assertSee($this->pedido->code);
            $this->actingAs($this->gestao);

            return $board;
        };

        expect(fn () => $boardOpenedBySuprimentos()->call('moveCard', $this->pedido->id, 0, $this->emAnalise->id))
            ->toThrow(AuthorizationException::class);
        expect(fn () => $boardOpenedBySuprimentos()->call('moveViaControl', $this->pedido->id, $this->emAnalise->id))
            ->toThrow(AuthorizationException::class);

        expect(adversarialPedidoState($this->pedido))->toBe($this->stateBefore);
    });
});
