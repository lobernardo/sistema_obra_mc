<?php

namespace App\Models;

use Database\Factories\PedidoEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['pedido_id', 'event_type_id', 'previous_value', 'new_value', 'actor_id'])]
class PedidoEvent extends Model
{
    /** @use HasFactory<PedidoEventFactory> */
    use HasFactory;

    /**
     * `pedido_events` is append-only: there is no `updated_at` column.
     */
    const UPDATED_AT = null;

    /**
     * Guard against mutation after creation: no route/action ever updates or
     * deletes a history event, and this model-level guard enforces it even
     * against a direct call bypassing the missing route (defense in depth).
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('PedidoEvent registros são imutáveis e não podem ser atualizados.');
        });

        static::deleting(function (): void {
            throw new LogicException('PedidoEvent registros são imutáveis e não podem ser excluídos.');
        });
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<EventType, $this>
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
