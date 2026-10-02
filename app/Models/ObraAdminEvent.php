<?php

namespace App\Models;

use App\Enums\ObraAdminAction;
use Database\Factories\ObraAdminEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One row of the obra/convite audit trail (CT-07 b, RF-02, RF-34), written
 * only by `App\Services\ObraAdminAuditRecorder`.
 *
 * `obra_id` and `obra_invitation_id` are `ON DELETE SET NULL`, so the row
 * survives the deletion of its obra or convite; `subject_obra_id` (no FK,
 * never nulled) keeps the trail groupable by obra afterwards (RF-19).
 */
#[Fillable(['actor_id', 'obra_id', 'subject_obra_id', 'obra_invitation_id', 'action', 'before', 'after'])]
class ObraAdminEvent extends Model
{
    /** @use HasFactory<ObraAdminEventFactory> */
    use HasFactory;

    /**
     * `obra_admin_events` is append-only: there is no `updated_at` column
     * (CT-07).
     */
    const UPDATED_AT = null;

    /**
     * Guard against mutation after creation (CT-07): no route, component or
     * Action ever updates or deletes an obra audit record, and this
     * model-level guard enforces it even against a direct call (defense in
     * depth). The single exemption is `demo:reset`, which deletes via
     * `DB::table(...)`.
     *
     * `subject_obra_id` defaults to `obra_id` on insert (RF-19), as defense
     * in depth for rows created outside the recorder.
     */
    protected static function booted(): void
    {
        static::creating(function (ObraAdminEvent $event): void {
            $event->subject_obra_id ??= $event->obra_id;
        });

        static::updating(function (): void {
            throw new LogicException('ObraAdminEvent registros são imutáveis e não podem ser atualizados.');
        });

        static::deleting(function (): void {
            throw new LogicException('ObraAdminEvent registros são imutáveis e não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return [
            'action' => ObraAdminAction::class,
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
     * @return BelongsTo<Obra, $this>
     */
    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    /**
     * @return BelongsTo<ObraInvitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ObraInvitation::class, 'obra_invitation_id');
    }
}
