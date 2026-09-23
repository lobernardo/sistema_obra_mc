<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Services\PedidoAttachmentStorage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorized download of a pedido attachment (RF-16..RF-18, RNF-01, RNF-09,
 * CT-03).
 *
 * A controller is the right layer here: the link is a plain GET that must
 * stream file bytes, while Livewire components render HTML. It writes
 * nothing. `PedidoPolicy::view` is re-checked on every request, so the URL is
 * never a bearer credential; the scoped binding answers 404 for an
 * attachment requested under another pedido, and a missing file is 404 too.
 * The response is always a download (`attachment`), with the stored MIME
 * type, `nosniff` and no caching.
 */
class PedidoAttachmentDownloadController extends Controller
{
    public function __invoke(Pedido $pedido, PedidoAttachment $attachment, PedidoAttachmentStorage $storage): StreamedResponse
    {
        Gate::authorize('view', $pedido);

        abort_unless($storage->exists($attachment->path), 404);

        return Storage::disk(PedidoAttachmentStorage::DISK)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
