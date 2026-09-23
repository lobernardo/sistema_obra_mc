<?php

namespace App\Actions\Pedidos;

use App\Actions\Pedidos\Concerns\GuardsOperationalMutation;
use App\Enums\EventTypeSlug;
use App\Enums\PedidoAttachmentKind;
use App\Enums\StatusSlug;
use App\Exceptions\Pedidos\PedidoTerminalStateException;
use App\Models\EventType;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Romaneio upload by Suprimentos (RF-30..RF-32, CT-04).
 *
 * The attachment is classified romaneio because it came through this path —
 * never from its name or content (RF-30, RF-31). Each upload adds 1
 * `romaneio` row and 1 `romaneio_anexado` event whose `new_value` is the
 * sanitized display name; nothing is replaced (RF-32).
 *
 * Allowed while the status is in `StatusSlug::finalizableFrom()` (active or
 * Entregue); Cancelado and Finalizado answer 409. `ensurePedidoIsNotTerminal`
 * is deliberately not used, because Entregue must accept a romaneio (RF-37).
 * The file is inspected (PDF, JPG, PNG by content, ≤ 10 MB) before any
 * write. The status is re-read under `lockForUpdate` and checked again inside
 * the transaction; if anything fails after the file was written, the file is
 * removed (best effort) so no stored file outlives a rolled-back row (RNF-02).
 */
class AttachRomaneioAction
{
    use GuardsOperationalMutation;

    public const string ERROR_KEY = 'romaneio';

    public function __construct(private readonly PedidoAttachmentStorage $attachmentStorage) {}

    /**
     * @throws AuthorizationException
     * @throws PedidoTerminalStateException
     * @throws ValidationException
     */
    public function execute(User $actor, Pedido $pedido, mixed $file): PedidoAttachment
    {
        $this->ensureActorIsSuprimentos($actor);
        $this->ensurePedidoIsFinalizable($pedido);

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([self::ERROR_KEY => 'Selecione o arquivo do romaneio.']);
        }

        $inspected = $this->attachmentStorage->inspect($file, PedidoAttachmentKind::Romaneio, self::ERROR_KEY);

        $path = null;

        try {
            return DB::transaction(function () use ($actor, $pedido, $file, $inspected, &$path): PedidoAttachment {
                $locked = Pedido::query()->whereKey($pedido->id)->lockForUpdate()->with('status')->firstOrFail();

                $this->ensurePedidoIsFinalizable($locked);

                $path = $this->attachmentStorage->store($locked, $file, $inspected['extension']);

                $attachment = PedidoAttachment::query()->create([
                    'pedido_id' => $locked->id,
                    'kind' => PedidoAttachmentKind::Romaneio,
                    'path' => $path,
                    'original_name' => $inspected['display_name'],
                    'mime_type' => $inspected['mime'],
                    'size_bytes' => $inspected['size'],
                    'uploaded_by' => $actor->id,
                ]);

                $locked->events()->create([
                    'event_type_id' => EventType::query()->where('slug', EventTypeSlug::RomaneioAnexado->value)->value('id'),
                    'previous_value' => null,
                    'new_value' => $inspected['display_name'],
                    'actor_id' => $actor->id,
                ]);

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                $this->attachmentStorage->deleteQuietly([$path]);
            }

            throw $exception;
        }
    }
}
