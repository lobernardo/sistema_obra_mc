<?php

namespace App\Models;

use App\Enums\UserAdminAction;
use Database\Factories\UserAdminEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['actor_id', 'target_id', 'action', 'before', 'after'])]
class UserAdminEvent extends Model
{
    /** @use HasFactory<UserAdminEventFactory> */
    use HasFactory;

    /**
     * `user_admin_events` is append-only: there is no `updated_at` column
     * (RF-23, CT-02).
     */
    const UPDATED_AT = null;

    /**
     * Guard against mutation after creation (RF-23): no route, component or
     * Action ever updates or deletes an administrative audit record, and
     * this model-level guard enforces it even against a direct call
     * bypassing the missing route (defense in depth). The single exemption
     * is `demo:reset`, which deletes via `DB::table(...)` (D-11).
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('UserAdminEvent registros são imutáveis e não podem ser atualizados.');
        });

        static::deleting(function (): void {
            throw new LogicException('UserAdminEvent registros são imutáveis e não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return [
            'action' => UserAdminAction::class,
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
