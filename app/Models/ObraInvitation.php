<?php

namespace App\Models;

use App\Enums\ObraInvitationState;
use Database\Factories\ObraInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One convite bound to an obra (CT-02). Only the SHA-256 digest of the
 * token is stored; the model never holds, casts or appends a plaintext
 * token (RNF-01, RF-38). `revoked_*`/`used_*` are outside `#[Fillable]`:
 * they are written only by the conditional UPDATEs built on
 * `scopeConsumable()` (RF-32).
 *
 * The expiry comparisons use the application clock (`now()`), not the
 * database's, so both must agree (UTC).
 */
#[Fillable(['obra_id', 'token_hash', 'created_by', 'expires_at'])]
#[Hidden(['token_hash'])]
class ObraInvitation extends Model
{
    /** @use HasFactory<ObraInvitationFactory> */
    use HasFactory;

    /**
     * `obra_invitations` has no `updated_at` column (CT-02).
     */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * One-way digest used for storage and lookup (RNF-01).
     */
    public static function hashToken(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Derived state (RF-26), by precedence: Utilizado > Revogado >
     * Expirado (`now() >= expires_at`) > Pendente.
     */
    public function state(): ObraInvitationState
    {
        if ($this->used_at !== null) {
            return ObraInvitationState::Utilizado;
        }

        if ($this->revoked_at !== null) {
            return ObraInvitationState::Revogado;
        }

        if (now()->greaterThanOrEqualTo($this->expires_at)) {
            return ObraInvitationState::Expirado;
        }

        return ObraInvitationState::Pendente;
    }

    /**
     * Validity per RF-27 / NC-07: pending and bound to an obra that is not
     * Concluído.
     */
    public function isConsumable(): bool
    {
        return $this->state() === ObraInvitationState::Pendente
            && $this->obra->status->isActive();
    }

    /**
     * SQL form of `isConsumable()` — the single predicate reused by the
     * conditional revocation/consumption UPDATEs (RF-27, RF-32).
     */
    #[Scope]
    protected function consumable(Builder $query): Builder
    {
        return $query
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereHas('obra', fn (Builder $obraQuery) => $obraQuery->active());
    }

    /**
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * The account that consumed the convite (`used_by`).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
