<?php

namespace App\Livewire\Notificacoes;

use App\Actions\Notificacoes\MarkAllInternalNotificationsReadAction;
use App\Actions\Notificacoes\MarkInternalNotificationReadAction;
use App\Domain\Pedidos\NotifiableEventTypes;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Pedido;
use App\Services\PedidoEventValuePresenter;
use App\Support\PedidoDetailRoute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Notificações Internas (CT-01, UI-01..UI-04): the authenticated user's own
 * notifications, most recent first, 20 per page.
 *
 * Every query opens with `InternalNotification::forRecipient`, the single
 * definition of notification visibility (RF-21, RNF-07); the filters are
 * applied afterwards and can only narrow it. The content of each item is
 * derived from its source event through `PedidoEventValuePresenter::describeEach`,
 * so the number of queries does not grow with the number of items.
 *
 * Filter state lives only in `#[Url]` properties with a neutral `except:`
 * (UI-04); an unknown `lidas` or `tipo` value is treated as neutral.
 *
 * Opening a notification (UI-02) marks it as read through
 * `MarkInternalNotificationReadAction` — which re-checks ownership, so a
 * forged id is a 404 with no navigation — and only then redirects to the
 * pedido detail route of the user's papel.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public const int PER_PAGE = 20;

    public const string READ_STATE_UNREAD = 'nao';

    public const string READ_STATE_READ = 'sim';

    #[Url(as: 'lidas', except: '')]
    public string $readState = '';

    #[Url(as: 'tipo', except: '')]
    public string $eventType = '';

    #[Url(as: 'codigo', except: '')]
    public string $code = '';

    public function mount(): void
    {
        $this->authorize('view-notifications');
    }

    public function updating(string $name): void
    {
        if ($name !== 'page') {
            $this->resetPage();
        }
    }

    /**
     * Resets every filter to its neutral value, so the URL carries no
     * parameter, and returns to page 1.
     */
    public function limparFiltros(): void
    {
        $this->reset(['readState', 'eventType', 'code']);

        $this->resetPage();
    }

    public function markAsRead(int $id, MarkInternalNotificationReadAction $action): void
    {
        $action->execute(Auth::user(), $id);

        $this->dispatch('notificacoes-atualizadas');
    }

    public function markAllAsRead(MarkAllInternalNotificationsReadAction $action): void
    {
        $action->execute(Auth::user());

        $this->dispatch('notificacoes-atualizadas');
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

    public function activeFilterCount(): int
    {
        return count(array_filter([
            $this->effectiveReadState() !== '',
            $this->effectiveEventType() !== '',
            trim($this->code) !== '',
        ]));
    }

    /**
     * @return LengthAwarePaginator<int, InternalNotification>
     */
    public function notifications(): LengthAwarePaginator
    {
        $query = InternalNotification::query()
            ->forRecipient(Auth::user())
            ->with(['pedido.obra', 'event.eventType', 'event.actor', 'event.pedido.obra']);

        match ($this->effectiveReadState()) {
            self::READ_STATE_UNREAD => $query->whereNull('read_at'),
            self::READ_STATE_READ => $query->whereNotNull('read_at'),
            default => null,
        };

        if ($this->effectiveEventType() !== '') {
            $query->where('event_type_slug', $this->effectiveEventType());
        }

        $code = trim($this->code);

        if ($code !== '') {
            $query->whereIn('pedido_id', Pedido::query()->where('code', 'ilike', '%'.addcslashes($code, '%_\\').'%')->select('pedidos.id'));
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    public function render()
    {
        $notifications = $this->notifications();

        return view('livewire.notificacoes.index', [
            'notifications' => $notifications,
            'descriptions' => app(PedidoEventValuePresenter::class)->describeEach(
                $notifications->getCollection()->map(fn (InternalNotification $notification) => $notification->event),
            ),
            'eventTypes' => $this->eventTypeOptions(),
        ]);
    }

    /**
     * The `tipo` options: the notifiable event types, in the order of their
     * classification.
     *
     * @return Collection<string, string> name keyed by slug
     */
    private function eventTypeOptions(): Collection
    {
        $slugs = $this->notifiableSlugs();
        $names = EventType::query()->whereIn('slug', $slugs)->pluck('name', 'slug');

        return collect($slugs)
            ->filter(fn (string $slug): bool => $names->has($slug))
            ->mapWithKeys(fn (string $slug): array => [$slug => $names->get($slug)]);
    }

    /**
     * @return list<string>
     */
    private function notifiableSlugs(): array
    {
        return array_keys(array_filter(app(NotifiableEventTypes::class)->classification()));
    }

    private function effectiveReadState(): string
    {
        return in_array($this->readState, [self::READ_STATE_UNREAD, self::READ_STATE_READ], true) ? $this->readState : '';
    }

    private function effectiveEventType(): string
    {
        return in_array($this->eventType, $this->notifiableSlugs(), true) ? $this->eventType : '';
    }
}
