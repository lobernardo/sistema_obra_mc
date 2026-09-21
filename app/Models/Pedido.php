<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'obra_id',
    'requester_id',
    'requested_at',
    'needed_at',
    'items_description',
    'status_id',
    'priority_id',
    'responsible_id',
    'expected_delivery_at',
    'is_demo',
])]
class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory;

    /**
     * Centralized visibility (RF-01, CT-01); inactive obras retain their
     * historical pedidos in the associated user's scope (D-06).
     *
     * @param  Builder<Pedido>  $query
     * @return Builder<Pedido>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        return match (RoleSlug::tryFrom((string) $user->role?->slug)) {
            RoleSlug::Obra => $query->whereIn('obra_id', $user->obras()->select('obras.id')),
            RoleSlug::Suprimentos, RoleSlug::Gestao => $query,
            default => $query->whereRaw('1 = 0'),
        };
    }

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'needed_at' => 'date',
            'expected_delivery_at' => 'date',
            'is_demo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * @return BelongsTo<Priority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * @return HasMany<PedidoEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PedidoEvent::class);
    }
}
