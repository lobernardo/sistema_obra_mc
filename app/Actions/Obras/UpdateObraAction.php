<?php

namespace App\Actions\Obras;

use App\Actions\Obras\Concerns\GuardsObraAdministration;
use App\Enums\ObraAdminAction;
use App\Models\Obra;
use App\Models\User;
use App\Services\ObraAdminAuditRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edits nome, responsável and status of an obra (RF-02). Any status may go
 * to any status, including from Concluído back to A iniciar/Em andamento.
 * Validation and the `lower(btrim(name))` uniqueness (excluding the obra
 * itself) are shared with `CreateObraAction`; a unique-index race is caught
 * outside the transaction and becomes the same 422 on `name`.
 *
 * Audit (CT-07 b): one `obra_updated` record holding only the changed keys
 * before/after, in the same transaction as the update. An identical
 * resubmission writes nothing. Only the `obras` row changes — no pedido,
 * `pedido_events` or `obra_profile` row is touched, and no Action deletes
 * an obra (RF-06).
 */
class UpdateObraAction
{
    use GuardsObraAdministration;

    public function __construct(private readonly ObraAdminAuditRecorder $recorder) {}

    /**
     * @param  array{name?: mixed, responsavel?: mixed, status?: mixed}  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, Obra $obra, array $data): Obra
    {
        $this->ensureActorManagesObras($actor);

        $validated = CreateObraAction::validate($data);

        CreateObraAction::ensureNameIsUnique($validated['name'], $obra);

        $before = $this->recorder->snapshot($obra);
        $after = [
            'name' => $validated['name'],
            'responsavel' => $validated['responsavel'],
            'status' => $validated['status']->value,
        ];

        $changedKeys = array_values(array_filter(
            array_keys($after),
            fn (string $key): bool => $before[$key] !== $after[$key],
        ));

        if ($changedKeys === []) {
            return $obra;
        }

        try {
            return DB::transaction(function () use ($actor, $obra, $validated, $before, $after, $changedKeys): Obra {
                $obra->update([
                    'name' => $validated['name'],
                    'responsavel' => $validated['responsavel'],
                    'status' => $validated['status'],
                ]);

                $this->recorder->record(
                    $actor,
                    $obra,
                    ObraAdminAction::ObraUpdated,
                    array_intersect_key($before, array_flip($changedKeys)),
                    array_intersect_key($after, array_flip($changedKeys)),
                );

                return $obra;
            });
        } catch (UniqueConstraintViolationException) {
            $obra->refresh();

            throw CreateObraAction::duplicateNameException();
        }
    }
}
