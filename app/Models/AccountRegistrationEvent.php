<?php

namespace App\Models;

use App\Enums\AccountOrigin;
use Database\Factories\AccountRegistrationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One row of the account-creation audit trail (CT-07 c, RF-22): the created
 * user, the origin, the convite when applicable and the client IP — never a
 * password or an e-mail.
 *
 * @property string|null $ip Client IP as seen by `Request::ip()` behind `trustProxies('*')` — `X-Forwarded-For`-derived, client-influenceable.
 */
#[Fillable(['user_id', 'origin', 'obra_invitation_id', 'ip'])]
class AccountRegistrationEvent extends Model
{
    /** @use HasFactory<AccountRegistrationEventFactory> */
    use HasFactory;

    /**
     * `account_registration_events` is append-only: there is no
     * `updated_at` column (CT-07).
     */
    const UPDATED_AT = null;

    /**
     * Guard against mutation after creation (CT-07, RF-22): this
     * model-level guard enforces immutability even against a direct call
     * (defense in depth). The single exemption is `demo:reset`, which
     * deletes via `DB::table(...)`.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('AccountRegistrationEvent registros são imutáveis e não podem ser atualizados.');
        });

        static::deleting(function (): void {
            throw new LogicException('AccountRegistrationEvent registros são imutáveis e não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return [
            'origin' => AccountOrigin::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ObraInvitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ObraInvitation::class, 'obra_invitation_id');
    }
}
