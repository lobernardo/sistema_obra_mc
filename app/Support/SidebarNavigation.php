<?php

namespace App\Support;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The per-papel sidebar catalogue (navegacao-sidebar-listagens CT-02),
 * in the order of RF-03, with the exact PT-BR labels of RNF-05.
 *
 * This class is **never an authorization layer** (`CLAUDE.md` §5): route
 * middleware, `mount()` checks, policies and Action guards stay the only
 * barriers. Each item lists exactly the `can:` abilities of its target
 * route, so an item is shown iff the user would pass that route (RF-02);
 * hiding an item never replaces the route's own 403. The gates only read
 * the already-loaded `role` relation, so building the list issues no query
 * once `role` is loaded (RNF-01).
 */
final class SidebarNavigation
{
    /**
     * The sidebar items the user may see, in display order. A `null` user or
     * an unrecognised papel gets no item.
     *
     * @return list<array{label: string, route: string, active: string, abilities: list<string>, group: ?string, highlight: bool}>
     */
    public static function for(?User $user): array
    {
        $roleSlug = RoleSlug::tryFrom($user?->role?->slug ?? '');

        if ($user === null || $roleSlug === null) {
            return [];
        }

        $gate = Gate::forUser($user);

        return array_values(array_filter(
            self::catalogue()[$roleSlug->value],
            function (array $item) use ($gate): bool {
                foreach ($item['abilities'] as $ability) {
                    if (! $gate->allows($ability)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * The full catalogue keyed by {@see RoleSlug} value, before the ability
     * filter, so tests iterate the same data the layout renders.
     *
     * @return array<string, list<array{label: string, route: string, active: string, abilities: list<string>, group: ?string, highlight: bool}>>
     */
    public static function catalogue(): array
    {
        return [
            RoleSlug::Obra->value => [
                self::item('+ Nova Solicitação', 'obra.nova-solicitacao', 'obra.nova-solicitacao', ['is-obra', 'create-pedido'], null, true),
                self::item('Acompanhamento', 'obra.pedidos.index', 'obra.pedidos.*', ['is-obra']),
            ],
            RoleSlug::Suprimentos->value => [
                self::item('+ Nova Solicitação', 'suprimentos.nova-solicitacao', 'suprimentos.nova-solicitacao', ['is-suprimentos', 'create-pedido'], null, true),
                self::item('Pedidos', 'suprimentos.pedidos.index', 'suprimentos.pedidos.*', ['is-suprimentos'], 'Operação'),
                self::item('Visão Geral', 'suprimentos.visao-geral', 'suprimentos.visao-geral', ['is-suprimentos'], 'Operação'),
                self::item('Kanban', 'suprimentos.kanban', 'suprimentos.kanban', ['is-suprimentos'], 'Operação'),
                self::item('Obras', 'obras.index', 'obras.*', ['manage-obras'], 'Cadastros'),
                self::item('Associações', 'associacoes.index', 'associacoes.*', ['manage-obras'], 'Cadastros'),
            ],
            RoleSlug::Gestao->value => [
                self::item('Pedidos', 'gestao.pedidos.index', 'gestao.pedidos.*', ['is-gestao'], 'Operação'),
                self::item('Dashboard', 'gestao.dashboard', 'gestao.dashboard', ['is-gestao'], 'Operação'),
                self::item('Kanban', 'gestao.kanban', 'gestao.kanban', ['is-gestao'], 'Operação'),
                self::item('Obras', 'obras.index', 'obras.*', ['manage-obras'], 'Administração'),
                self::item('Associações', 'associacoes.index', 'associacoes.*', ['manage-obras'], 'Administração'),
                self::item('Usuários', 'gestao.usuarios.index', 'gestao.usuarios.*', ['is-gestao', 'manage-users'], 'Administração'),
            ],
        ];
    }

    /**
     * @param  list<string>  $abilities
     * @return array{label: string, route: string, active: string, abilities: list<string>, group: ?string, highlight: bool}
     */
    private static function item(string $label, string $route, string $active, array $abilities, ?string $group = null, bool $highlight = false): array
    {
        return [
            'label' => $label,
            'route' => $route,
            'active' => $active,
            'abilities' => $abilities,
            'group' => $group,
            'highlight' => $highlight,
        ];
    }
}
