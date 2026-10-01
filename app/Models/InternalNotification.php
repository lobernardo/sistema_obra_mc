<?php

namespace App\Models;

use App\Enums\InternalNotificationEmailStatus;
use Database\Factories\InternalNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['recipient_id', 'pedido_id', 'pedido_event_id', 'event_type_slug', 'actor_id', 'read_at', 'email_status', 'email_status_at'])]
class InternalNotification extends Model
{
    /** @use HasFactory<InternalNotificationFactory> */
    use HasFactory;

    /**
     * `internal_notifications` has no `updated_at` column (RF-22).
     */
    const UPDATED_AT = null;

    /**
     * The only columns an existing notification may change: the read
     * timestamp (RF-18, RF-19) and the e-mail delivery state (RF-16).
     *
     * @var list<string>
     */
    public const MUTABLE_COLUMNS = ['read_at', 'email_status', 'email_status_at'];

    /**
     * Append-only except for {@see self::MUTABLE_COLUMNS} (RF-22), in the
     * same pattern as `PedidoEvent`. `demo:reset` deletes demo rows through
     * the query builder, never through this model (RF-23).
     */
    protected static function booted(): void
    {
        static::updating(function (InternalNotification $notification): void {
            $forbidden = array_diff(array_keys($notification->getDirty()), self::MUTABLE_COLUMNS);

            if ($forbidden !== []) {
                throw new LogicException('InternalNotification registros são imutáveis, exceto a leitura e o estado do e-mail.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('InternalNotification registros são imutáveis e não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'read_at' => 'datetime',
            'email_status' => InternalNotificationEmailStatus::class,
            'email_status_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<PedidoEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(PedidoEvent::class, 'pedido_event_id');
    }

    /**
     * The single definition of which notifications a user may see, list,
     * count or mark (RF-21, RNF-07): the user's own rows whose pedido the
     * user may currently view through `Pedido::visibleTo`. It opens every
     * notification query, before any filter, so a filter can only narrow it.
     *
     * @param  Builder<InternalNotification>  $query
     * @return Builder<InternalNotification>
     */
    #[Scope]
    protected function forRecipient(Builder $query, User $user): Builder
    {
        return $query
            ->where('internal_notifications.recipient_id', $user->id)
            ->whereIn('internal_notifications.pedido_id', Pedido::query()->visibleTo($user)->select('pedidos.id'));
    }
}
