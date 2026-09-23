<?php

namespace App\Models;

use App\Enums\PedidoAttachmentKind;
use Database\Factories\PedidoAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['pedido_id', 'kind', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'])]
#[Hidden(['path'])]
class PedidoAttachment extends Model
{
    /** @use HasFactory<PedidoAttachmentFactory> */
    use HasFactory;

    /**
     * `pedido_attachments` is append-only: there is no `updated_at` column.
     */
    const UPDATED_AT = null;

    /**
     * Guard against mutation after creation (RF-19): no route/action ever
     * updates or deletes an attachment, and this model-level guard enforces
     * it even against a direct call. `demo:reset` deletes demo rows through
     * the query builder, never through this model.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('PedidoAttachment registros são imutáveis e não podem ser atualizados.');
        });

        static::deleting(function (): void {
            throw new LogicException('PedidoAttachment registros são imutáveis e não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return [
            'kind' => PedidoAttachmentKind::class,
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
