<?php

namespace Database\Seeders;

use App\Enums\EventTypeSlug;
use App\Enums\PrioritySlug;
use App\Enums\RoleSlug;
use App\Enums\StatusSlug;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Priority;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Populates the lookup tables plus a demonstration dataset (users, obras,
 * pedidos, history events) so the app is usable out of the box (RF-06).
 * Every write is keyed by a natural key (slug, email, obra name, pedido
 * code) so re-running the seeder never duplicates rows or trips a unique
 * constraint. `demo:reset` (T47) relies on the `is_demo = true` flag this
 * seeder stamps on every user/obra/pedido row it writes.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = $this->seedRoles();
            $statuses = $this->seedStatuses();
            $priorities = $this->seedPriorities();
            $eventTypes = $this->seedEventTypes();

            $users = $this->seedUsers($roles);
            $obras = $this->seedObras($users);

            $this->seedPedidos($users, $obras, $statuses, $priorities, $eventTypes);
        });
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $definitions = [
            RoleSlug::Obra->value => 'Obra',
            RoleSlug::Suprimentos->value => 'Suprimentos',
            RoleSlug::Gestao->value => 'Gestão',
        ];

        return collect($definitions)
            ->mapWithKeys(fn (string $name, string $slug) => [
                $slug => Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name]),
            ])
            ->all();
    }

    /**
     * @return array<string, Status>
     */
    private function seedStatuses(): array
    {
        $definitions = [
            StatusSlug::Solicitado->value => ['Solicitado', 1],
            StatusSlug::EmAnalise->value => ['Em análise', 2],
            StatusSlug::EmCompraPreparacao->value => ['Em compra/preparação', 3],
            StatusSlug::AguardandoEntrega->value => ['Aguardando entrega', 4],
            StatusSlug::Entregue->value => ['Entregue', 5],
            StatusSlug::Cancelado->value => ['Cancelado', 6],
        ];

        return collect($definitions)
            ->mapWithKeys(fn (array $definition, string $slug) => [
                $slug => Status::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $definition[0], 'sort_order' => $definition[1]],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, Priority>
     */
    private function seedPriorities(): array
    {
        $definitions = [
            PrioritySlug::Baixa->value => ['Baixa', 1],
            PrioritySlug::Normal->value => ['Normal', 2],
            PrioritySlug::Alta->value => ['Alta', 3],
            PrioritySlug::Urgente->value => ['Urgente', 4],
        ];

        return collect($definitions)
            ->mapWithKeys(fn (array $definition, string $slug) => [
                $slug => Priority::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $definition[0], 'sort_order' => $definition[1]],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, EventType>
     */
    private function seedEventTypes(): array
    {
        $definitions = [
            EventTypeSlug::CriacaoPedido->value => 'Criação do pedido',
            EventTypeSlug::MudancaStatus->value => 'Mudança de status',
            EventTypeSlug::AlteracaoResponsavel->value => 'Alteração de responsável',
            EventTypeSlug::AlteracaoPrioridade->value => 'Alteração de prioridade',
            EventTypeSlug::AlteracaoPrevisao->value => 'Alteração de previsão',
            EventTypeSlug::Cancelamento->value => 'Cancelamento',
            EventTypeSlug::Entrega->value => 'Entrega',
        ];

        return collect($definitions)
            ->mapWithKeys(fn (string $name, string $slug) => [
                $slug => EventType::query()->firstOrCreate(['slug' => $slug], ['name' => $name]),
            ])
            ->all();
    }

    /**
     * @param  array<string, Role>  $roles
     * @return array<string, User>
     */
    private function seedUsers(array $roles): array
    {
        $definitions = [
            'obra' => ['[DEMO] Usuário Obra', 'obra.demo@example.com', RoleSlug::Obra],
            'obra_multi' => ['[DEMO] Usuário Obra (Multi-obra)', 'obra.multiobra.demo@example.com', RoleSlug::Obra],
            'suprimentos' => ['[DEMO] Usuário Suprimentos', 'suprimentos.demo@example.com', RoleSlug::Suprimentos],
            'gestao' => ['[DEMO] Usuário Gestão', 'gestao.demo@example.com', RoleSlug::Gestao],
        ];

        return collect($definitions)
            ->mapWithKeys(function (array $definition, string $key) use ($roles) {
                [$name, $email, $roleSlug] = $definition;

                $user = User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                        'role_id' => $roles[$roleSlug->value]->id,
                        'is_active' => true,
                        'is_demo' => true,
                        'email_verified_at' => now(),
                    ],
                );

                return [$key => $user];
            })
            ->all();
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, Obra>
     */
    private function seedObras(array $users): array
    {
        $names = [
            'alfa' => '[DEMO] Obra Alfa',
            'beta' => '[DEMO] Obra Beta',
            'gama' => '[DEMO] Obra Gama',
        ];

        $obras = collect($names)
            ->mapWithKeys(fn (string $name, string $key) => [
                $key => Obra::query()->firstOrCreate(['name' => $name], ['is_active' => true, 'is_demo' => true]),
            ])
            ->all();

        // "obra" is associated with a single obra; "obra_multi" demonstrates
        // multi-obra access (RF-10) across two obras.
        $obras['alfa']->users()->syncWithoutDetaching([$users['obra']->id]);
        $obras['beta']->users()->syncWithoutDetaching([$users['obra_multi']->id]);
        $obras['gama']->users()->syncWithoutDetaching([$users['obra_multi']->id]);

        return $obras;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Obra>  $obras
     * @param  array<string, Status>  $statuses
     * @param  array<string, Priority>  $priorities
     * @param  array<string, EventType>  $eventTypes
     */
    private function seedPedidos(array $users, array $obras, array $statuses, array $priorities, array $eventTypes): void
    {
        $today = Carbon::today();

        $definitions = [
            [
                'code' => 'PED-DEMO-0001',
                'obra' => 'alfa',
                'requester' => 'obra',
                'status' => StatusSlug::Solicitado,
                'priority' => null,
                'responsible' => null,
                'needed_at' => $today->copy()->addDays(10),
                'expected_delivery_at' => null,
            ],
            [
                'code' => 'PED-DEMO-0002',
                'obra' => 'alfa',
                'requester' => 'obra',
                'status' => StatusSlug::EmAnalise,
                'priority' => PrioritySlug::Alta,
                'responsible' => 'suprimentos',
                'needed_at' => $today->copy()->addDays(5),
                'expected_delivery_at' => null,
            ],
            [
                'code' => 'PED-DEMO-0003',
                'obra' => 'beta',
                'requester' => 'obra_multi',
                'status' => StatusSlug::EmCompraPreparacao,
                'priority' => PrioritySlug::Urgente,
                'responsible' => 'suprimentos',
                // Past due date on a non-terminal pedido: classified as
                // atrasado (RF-19).
                'needed_at' => $today->copy()->subDays(2),
                'expected_delivery_at' => $today->copy()->addDays(3),
            ],
            [
                'code' => 'PED-DEMO-0004',
                'obra' => 'beta',
                'requester' => 'obra_multi',
                'status' => StatusSlug::AguardandoEntrega,
                'priority' => PrioritySlug::Baixa,
                'responsible' => 'suprimentos',
                'needed_at' => $today->copy()->addDay(),
                'expected_delivery_at' => $today->copy()->addDays(2),
            ],
            [
                'code' => 'PED-DEMO-0005',
                'obra' => 'gama',
                'requester' => 'obra_multi',
                'status' => StatusSlug::Entregue,
                'priority' => PrioritySlug::Normal,
                'responsible' => 'suprimentos',
                // Past due but delivered: never atrasado regardless of date
                // (RF-19).
                'needed_at' => $today->copy()->subDays(10),
                'expected_delivery_at' => $today->copy()->subDay(),
            ],
            [
                'code' => 'PED-DEMO-0006',
                'obra' => 'gama',
                'requester' => 'obra_multi',
                'status' => StatusSlug::Cancelado,
                'priority' => PrioritySlug::Baixa,
                'responsible' => 'suprimentos',
                'needed_at' => $today->copy()->addDays(7),
                'expected_delivery_at' => null,
            ],
        ];

        foreach ($definitions as $definition) {
            $this->seedPedido($definition, $users, $obras, $statuses, $priorities, $eventTypes);
        }
    }

    /**
     * @param  array{code: string, obra: string, requester: string, status: StatusSlug, priority: ?PrioritySlug, responsible: ?string, needed_at: Carbon, expected_delivery_at: ?Carbon}  $definition
     * @param  array<string, User>  $users
     * @param  array<string, Obra>  $obras
     * @param  array<string, Status>  $statuses
     * @param  array<string, Priority>  $priorities
     * @param  array<string, EventType>  $eventTypes
     */
    private function seedPedido(array $definition, array $users, array $obras, array $statuses, array $priorities, array $eventTypes): void
    {
        if (Pedido::query()->where('code', $definition['code'])->exists()) {
            return;
        }

        $requester = $users[$definition['requester']];
        $obra = $obras[$definition['obra']];
        $finalStatus = $statuses[$definition['status']->value];
        $solicitadoStatus = $statuses[StatusSlug::Solicitado->value];
        $priority = $definition['priority'] !== null ? $priorities[$definition['priority']->value] : null;
        $responsible = $definition['responsible'] !== null ? $users[$definition['responsible']] : null;
        $actorId = $responsible?->id ?? $requester->id;

        $pedido = Pedido::query()->create([
            'code' => $definition['code'],
            'obra_id' => $obra->id,
            'requester_id' => $requester->id,
            'needed_at' => $definition['needed_at']->toDateString(),
            'items_description' => "[DEMO] Itens do pedido {$definition['code']}",
            'status_id' => $finalStatus->id,
            'priority_id' => $priority?->id,
            'responsible_id' => $responsible?->id,
            'expected_delivery_at' => $definition['expected_delivery_at']?->toDateString(),
            'is_demo' => true,
        ]);

        $pedido->events()->create([
            'event_type_id' => $eventTypes[EventTypeSlug::CriacaoPedido->value]->id,
            'actor_id' => $requester->id,
        ]);

        if ($responsible !== null) {
            $pedido->events()->create([
                'event_type_id' => $eventTypes[EventTypeSlug::AlteracaoResponsavel->value]->id,
                'previous_value' => null,
                'new_value' => (string) $responsible->id,
                'actor_id' => $actorId,
            ]);
        }

        if ($priority !== null) {
            $pedido->events()->create([
                'event_type_id' => $eventTypes[EventTypeSlug::AlteracaoPrioridade->value]->id,
                'previous_value' => null,
                'new_value' => (string) $priority->id,
                'actor_id' => $actorId,
            ]);
        }

        if ($definition['expected_delivery_at'] !== null) {
            $pedido->events()->create([
                'event_type_id' => $eventTypes[EventTypeSlug::AlteracaoPrevisao->value]->id,
                'previous_value' => null,
                'new_value' => $definition['expected_delivery_at']->toDateString(),
                'actor_id' => $actorId,
            ]);
        }

        $statusEventTypeSlug = match ($definition['status']) {
            StatusSlug::Cancelado => EventTypeSlug::Cancelamento,
            StatusSlug::Entregue => EventTypeSlug::Entrega,
            StatusSlug::Solicitado => null,
            default => EventTypeSlug::MudancaStatus,
        };

        if ($statusEventTypeSlug !== null) {
            $pedido->events()->create([
                'event_type_id' => $eventTypes[$statusEventTypeSlug->value]->id,
                'previous_value' => (string) $solicitadoStatus->id,
                'new_value' => (string) $finalStatus->id,
                'actor_id' => $actorId,
            ]);
        }
    }
}
