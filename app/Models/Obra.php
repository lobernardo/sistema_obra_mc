<?php

namespace App\Models;

use App\Enums\ObraStatus;
use Database\Factories\ObraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An obra. Its lifecycle `status` is purely descriptive; whether it is
 * "ativa" (accepts new pedidos, convites and associations) is the
 * independent `is_active` flag, read only through `isActive()` and the
 * `active()` scope — the single definition of "obra ativa" (RF-03).
 */
#[Fillable(['name', 'responsavel', 'status', 'is_active', 'is_demo'])]
class Obra extends Model
{
    /** @use HasFactory<ObraFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ObraStatus::class,
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    /**
     * The single definition of "obra ativa" (RF-03): `is_active = true`,
     * whatever the Status. `active()` is its SQL twin.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * SQL form of `isActive()`: every obra with `is_active = true` (RF-03).
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Label of the obra in the pedido-listing and dashboard obra filters:
     * the name, suffixed with " (inativa)" for an inactive obra (UI-07).
     */
    public function filterOptionLabel(): string
    {
        return $this->isActive() ? $this->name : $this->name.' (inativa)';
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'obra_profile');
    }

    /**
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /**
     * @return HasMany<ObraInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(ObraInvitation::class);
    }
}
