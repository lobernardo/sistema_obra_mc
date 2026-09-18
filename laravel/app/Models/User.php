<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleSlug;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'is_active', 'is_demo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsToMany<Obra, $this>
     */
    public function obras(): BelongsToMany
    {
        return $this->belongsToMany(Obra::class, 'obra_profile');
    }

    /**
     * @return HasMany<Pedido, $this>
     */
    public function requestedPedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'requester_id');
    }

    /**
     * @return HasMany<Pedido, $this>
     */
    public function responsiblePedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'responsible_id');
    }

    /**
     * @return HasMany<PedidoEvent, $this>
     */
    public function pedidoEvents(): HasMany
    {
        return $this->hasMany(PedidoEvent::class, 'actor_id');
    }

    /**
     * Scope a query to users with the `suprimentos` papel, for selectors
     * restricted to that role (RF-14b), e.g. the pedido responsible-selector.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeSuprimentos(Builder $query): Builder
    {
        return $query->whereHas('role', fn (Builder $query) => $query->where('slug', RoleSlug::Suprimentos->value));
    }
}
