<?php

namespace App\Models;

use App\Enums\AuthenticationEventType;
use Database\Factories\AuthenticationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One row of the authentication trail (CT-03, RF-26..RF-29), written only
 * by `App\Services\AuthenticationEventRecorder`. Holds the event slug, the
 * user (nullable), the normalized e-mail, the client IP, a sanitised user
 * agent and `created_at` — never a password, hash, token, session id or
 * cookie (RF-27).
 *
 * `ip` is `Request::ip()` as resolved behind `trustProxies(at: '*')`
 * (`bootstrap/app.php`): it is derived from the `X-Forwarded-For` header
 * and can therefore be influenced by the client. Treat it as an indicative
 * value, not as proof of origin (D-01, residual risk R-01).
 *
 * Rows are stored without automatic retention, purge or anonymisation in
 * this feature (D-07, residual risk R-02); the only removal is the demo
 * cleanup performed by `demo:reset` through `DB::table(...)` (D-05, D-11).
 *
 * @property string|null $ip Client IP as seen by `Request::ip()` behind `trustProxies('*')` — `X-Forwarded-For`-derived, client-influenceable (D-01).
 */
#[Fillable(['event', 'user_id', 'email', 'ip', 'user_agent'])]
class AuthenticationEvent extends Model
{
    /** @use HasFactory<AuthenticationEventFactory> */
    use HasFactory;

    /**
     * `authentication_events` is append-only: there is no `updated_at`
     * column (RF-27, CT-03).
     */
    const UPDATED_AT = null;

    /**
     * Guard against mutation after creation (RF-27): no route, component,
     * Action or service ever updates or deletes an authentication record,
     * and this model-level guard enforces it even against a direct call
     * (defense in depth). The single exemption is `demo:reset`, which
     * deletes via `DB::table(...)` (D-11).
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('AuthenticationEvent registros são imutáveis e não podem ser atualizados.');
        });

        static::deleting(function (): void {
            throw new LogicException('AuthenticationEvent registros são imutáveis e não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return [
            'event' => AuthenticationEventType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
