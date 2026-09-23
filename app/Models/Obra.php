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

#[Fillable(['name', 'responsavel', 'status', 'is_demo'])]
class Obra extends Model
{
    /** @use HasFactory<ObraFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ObraStatus::class,
            'is_demo' => 'boolean',
        ];
    }

    /**
     * SQL form of `ObraStatus::isActive()`: every obra that is not Concluído (RF-03).
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', '!=', ObraStatus::Concluido->value);
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
