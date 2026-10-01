<?php

namespace App\Livewire\Notificacoes;

use App\Actions\Notificacoes\MarkAllInternalNotificationsReadAction;
use App\Actions\Notificacoes\MarkInternalNotificationReadAction;
use App\Models\InternalNotification;
use App\Services\PedidoEventValuePresenter;
use App\Support\PedidoDetailRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The global bell (UI-05..UI-07, UI-10): the count of the current user's
 * unread notifications, rendered once by `layouts/app.blade.php`.
 *
 * A render issues exactly one query — the unread count, opened by
 * `InternalNotification::forRecipient` (RF-21, RNF-04, RNF-07). The panel
 * list is lazy: it is only queried after the user opens the bell
 * ({@see self::loadPanel()}). The counter refreshes on
 * `notificacoes-atualizadas`, on every full page load and through the Alpine
 * `refreshIfVisible()` timer, which never issues a request while the tab is
 * hidden (UI-07).
 *
 * Opening an item (UI-02) follows the same path as the Notificações Internas
 * page: `MarkInternalNotificationReadAction` re-checks ownership — a forged id
 * is a 404 with no navigation — and only then redirects to the pedido detail
 * route of the user's papel.
 */
class Bell extends Component
{
    public const int PANEL_LIMIT = 10;

    public bool $panelLoaded = false;

    /**
     * Loads the 10 most recent unread notifications into the panel (UI-06).
     */
    public function loadPanel(): void
    {
        $this->panelLoaded = true;
    }

    #[On('notificacoes-atualizadas')]
    public function refreshCounter(): void
    {
        // Re-rendering recomputes the counter (UI-03).
    }

    /**
     * Marks the notification as read (idempotent) and navigates to the
     * pedido detail of the user's papel (UI-02, Q-07).
     */
    public function abrir(int $id, MarkInternalNotificationReadAction $action): void
    {
        $user = Auth::user();
        $notification = $action->execute($user, $id);

        $this->redirectRoute(PedidoDetailRoute::nameFor($user), ['pedido' => $notification->pedido]);
    }

    public function markAllAsRead(MarkAllInternalNotificationsReadAction $action): void
    {
        $action->execute(Auth::user());

        $this->dispatch('notificacoes-atualizadas');
    }

    public function render()
    {
        $user = Auth::user();
        $enabled = $user !== null && Gate::forUser($user)->allows('view-notifications');

        $unreadCount = $enabled
            ? InternalNotification::query()->forRecipient($user)->whereNull('read_at')->count()
            : 0;

        $panelNotifications = $enabled && $this->panelLoaded ? $this->panelNotifications() : collect();

        return view('livewire.notificacoes.bell', [
            'enabled' => $enabled,
            'unreadCount' => $unreadCount,
            'badge' => $unreadCount > 9 ? '9+' : (string) $unreadCount,
            'panelNotifications' => $panelNotifications,
            'descriptions' => $panelNotifications->isEmpty()
                ? []
                : app(PedidoEventValuePresenter::class)->describeEach(
                    $panelNotifications->map(fn (InternalNotification $notification) => $notification->event),
                ),
        ]);
    }

    /**
     * @return Collection<int, InternalNotification>
     */
    private function panelNotifications(): Collection
    {
        return InternalNotification::query()
            ->forRecipient(Auth::user())
            ->whereNull('read_at')
            ->with(['pedido', 'event.eventType', 'event.actor', 'event.pedido.obra'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PANEL_LIMIT)
            ->get();
    }
}
