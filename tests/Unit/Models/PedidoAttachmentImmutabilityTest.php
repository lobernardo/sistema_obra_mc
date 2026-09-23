<?php

use App\Enums\PedidoAttachmentKind;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * CT-03, RF-19: `pedido_attachments` is append-only, like `pedido_events`,
 * and the database pins its classification and size invariants.
 */
test('updating a PedidoAttachment after creation throws', function () {
    $attachment = PedidoAttachment::factory()->create();

    expect(fn () => $attachment->update(['original_name' => 'tampered.pdf']))
        ->toThrow(LogicException::class);
    expect($attachment->fresh()->original_name)->toBe('documento.pdf');
});

test('saving a changed existing PedidoAttachment throws', function () {
    $attachment = PedidoAttachment::factory()->create();
    $attachment->mime_type = 'text/html';

    expect(fn () => $attachment->save())->toThrow(LogicException::class);
    expect($attachment->fresh()->mime_type)->toBe('application/pdf');
});

test('deleting a PedidoAttachment throws', function () {
    $attachment = PedidoAttachment::factory()->create();

    expect(fn () => $attachment->delete())->toThrow(LogicException::class);
    expect(PedidoAttachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
});

test('PedidoAttachmentPolicy denies update and delete for every actor', function () {
    $attachment = PedidoAttachment::factory()->create();

    foreach ([User::factory()->obra()->create(), User::factory()->suprimentos()->create(), User::factory()->gestao()->create()] as $user) {
        expect($user->can('update', $attachment))->toBeFalse();
        expect($user->can('delete', $attachment))->toBeFalse();
    }
});

test('the model casts kind, hides path and exposes its relations', function () {
    $attachment = PedidoAttachment::factory()->romaneio()->create();

    expect($attachment->fresh()->kind)->toBe(PedidoAttachmentKind::Romaneio);
    expect($attachment->kind->label())->toBe('Romaneio');
    expect($attachment->toArray())->not->toHaveKey('path');
    expect($attachment->pedido)->toBeInstanceOf(Pedido::class);
    expect($attachment->uploader)->toBeInstanceOf(User::class);
});

test('the database rejects an unknown kind', function () {
    $attachment = PedidoAttachment::factory()->create();

    expect(fn () => DB::table('pedido_attachments')->insert([
        'pedido_id' => $attachment->pedido_id,
        'kind' => 'outro',
        'path' => bin2hex(random_bytes(20)),
        'original_name' => 'x.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
        'uploaded_by' => $attachment->uploaded_by,
    ]))->toThrow(QueryException::class, 'pedido_attachments_kind_check');
});

test('the database rejects a zero size', function () {
    $attachment = PedidoAttachment::factory()->create();

    expect(fn () => DB::table('pedido_attachments')->insert([
        'pedido_id' => $attachment->pedido_id,
        'kind' => 'anexo',
        'path' => bin2hex(random_bytes(20)),
        'original_name' => 'x.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 0,
        'uploaded_by' => $attachment->uploaded_by,
    ]))->toThrow(QueryException::class, 'pedido_attachments_size_bytes_check');
});

test('deleting the pedido through the query builder cascades to its attachments', function () {
    $attachment = PedidoAttachment::factory()->create();

    DB::table('pedidos')->where('id', $attachment->pedido_id)->delete();

    expect(DB::table('pedido_attachments')->where('id', $attachment->id)->exists())->toBeFalse();
});
