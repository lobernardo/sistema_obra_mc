<?php

use App\Enums\AccountOrigin;
use App\Enums\EventTypeSlug;
use App\Enums\StatusSlug;
use App\Models\AccountRegistrationEvent;
use App\Models\EventType;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use App\Services\PedidoAttachmentStorage;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('reset removes only demo rows and preserves real data integrally', function () {
    $this->seed(DemoSeeder::class);

    $demoUserCount = User::query()->where('is_demo', true)->count();
    $demoObraCount = Obra::query()->where('is_demo', true)->count();
    $demoPedidoCount = Pedido::query()->where('is_demo', true)->count();

    expect($demoUserCount)->toBeGreaterThan(0);
    expect($demoObraCount)->toBeGreaterThan(0);
    expect($demoPedidoCount)->toBeGreaterThan(0);

    // A real dataset, kept self-contained (its own obra/requester/responsible)
    // so it never references a demo row.
    $realObra = Obra::factory()->create(['is_demo' => false]);
    $realRequester = User::factory()->obra()->create(['is_demo' => false]);
    $realResponsible = User::factory()->suprimentos()->create(['is_demo' => false]);
    $realObra->users()->attach($realRequester->id);

    $solicitado = Status::query()->where('slug', StatusSlug::Solicitado->value)->firstOrFail();

    $realPedido = Pedido::factory()->create([
        'obra_id' => $realObra->id,
        'requester_id' => $realRequester->id,
        'responsible_id' => $realResponsible->id,
        'status_id' => $solicitado->id,
        'is_demo' => false,
    ]);

    $criacaoPedido = EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->firstOrFail();

    $realEvent = $realPedido->events()->create([
        'event_type_id' => $criacaoPedido->id,
        'actor_id' => $realRequester->id,
    ]);

    Artisan::call('demo:reset', ['--force' => true]);

    expect(User::query()->where('is_demo', true)->count())->toBe(0);
    expect(Obra::query()->where('is_demo', true)->count())->toBe(0);
    expect(Pedido::query()->where('is_demo', true)->count())->toBe(0);

    expect(User::query()->whereKey($realRequester->id)->exists())->toBeTrue();
    expect(User::query()->whereKey($realResponsible->id)->exists())->toBeTrue();
    expect(Obra::query()->whereKey($realObra->id)->exists())->toBeTrue();
    expect(Pedido::query()->whereKey($realPedido->id)->exists())->toBeTrue();
    expect(PedidoEvent::query()->whereKey($realEvent->id)->exists())->toBeTrue();

    $realPedido->refresh();
    expect($realPedido->obra_id)->toBe($realObra->id);
    expect($realPedido->requester_id)->toBe($realRequester->id);
    expect($realPedido->responsible_id)->toBe($realResponsible->id);
    expect($realPedido->is_demo)->toBeFalse();
});

test('reset declines without --force when the confirmation prompt is rejected', function () {
    $this->seed(DemoSeeder::class);

    $demoUserCount = User::query()->where('is_demo', true)->count();

    $this->artisan('demo:reset')
        ->expectsConfirmation('Remover todos os dados de demonstração (is_demo = true)?', 'no')
        ->assertExitCode(0);

    expect(User::query()->where('is_demo', true)->count())->toBe($demoUserCount);
});

test('reset removes demo convites and their audit rows and keeps the real ones (RF-35)', function () {
    $this->seed(DemoSeeder::class);

    $demoObra = Obra::query()->where('is_demo', true)->firstOrFail();
    $demoGestao = User::query()->where('is_demo', true)->firstOrFail();

    $realObra = Obra::factory()->create(['is_demo' => false]);
    $realGestao = User::factory()->gestao()->create(['is_demo' => false]);
    $realObraUser = User::factory()->obra()->create(['is_demo' => false]);

    $demoPending = ObraInvitation::factory()->for($demoObra)->create(['created_by' => $demoGestao->id]);
    $demoUsed = ObraInvitation::factory()->for($demoObra)->used()->create(['created_by' => $demoGestao->id]);
    $demoRevoked = ObraInvitation::factory()->for($demoObra)->revoked()->create(['created_by' => $demoGestao->id]);
    $realInvitation = ObraInvitation::factory()->for($realObra)->create(['created_by' => $realGestao->id]);

    ObraAdminEvent::factory()->create(['actor_id' => $demoGestao->id, 'obra_id' => $demoObra->id, 'obra_invitation_id' => $demoUsed->id]);
    AccountRegistrationEvent::factory()->create(['user_id' => $demoUsed->used_by, 'origin' => AccountOrigin::Convite, 'obra_invitation_id' => $demoUsed->id]);

    $realAudit = ObraAdminEvent::factory()->create(['actor_id' => $realGestao->id, 'obra_id' => $realObra->id, 'obra_invitation_id' => $realInvitation->id]);
    $realRegistration = AccountRegistrationEvent::factory()->create(['user_id' => $realObraUser->id, 'origin' => AccountOrigin::NovoCadastro]);

    $this->artisan('demo:reset', ['--force' => true])->assertExitCode(0);

    expect(ObraInvitation::query()->whereKey([$demoPending->id, $demoUsed->id, $demoRevoked->id])->exists())->toBeFalse();
    expect(ObraInvitation::query()->pluck('id')->all())->toBe([$realInvitation->id]);
    expect(ObraAdminEvent::query()->pluck('id')->all())->toBe([$realAudit->id]);
    expect(AccountRegistrationEvent::query()->pluck('id')->all())->toBe([$realRegistration->id]);
    expect(Obra::query()->where('is_demo', true)->count())->toBe(0);
    expect(User::query()->where('is_demo', true)->count())->toBe(0);
});

/**
 * Writes an attachment row and its file on the private disk.
 */
function resetDemoAttachment(Pedido $pedido, User $uploader): PedidoAttachment
{
    $path = $pedido->id.'/'.bin2hex(random_bytes(20)).'.pdf';
    Storage::disk(PedidoAttachmentStorage::DISK)->put($path, anexoPdfBytes());

    return PedidoAttachment::factory()->create([
        'pedido_id' => $pedido->id,
        'path' => $path,
        'uploaded_by' => $uploader->id,
    ]);
}

test('reset deletes the attachment rows and files of demo pedidos and keeps the real ones (RF-19, RF-43)', function () {
    Storage::fake(PedidoAttachmentStorage::DISK);
    $this->seed(DemoSeeder::class);

    $demoPedido = Pedido::query()->where('is_demo', true)->firstOrFail();
    $demoUploader = User::query()->where('is_demo', true)->where('email', 'obra.demo@example.com')->firstOrFail();
    $demoAttachment = resetDemoAttachment($demoPedido, $demoUploader);

    $realObra = Obra::factory()->create(['is_demo' => false]);
    $realRequester = User::factory()->obra()->create(['is_demo' => false]);
    $realPedido = Pedido::factory()->create([
        'obra_id' => $realObra->id,
        'requester_id' => $realRequester->id,
        'status_id' => Status::query()->where('slug', StatusSlug::Solicitado->value)->value('id'),
        'is_demo' => false,
    ]);
    $realAttachment = resetDemoAttachment($realPedido, $realRequester);

    $this->artisan('demo:reset', ['--force' => true])->assertExitCode(0);

    $disk = Storage::disk(PedidoAttachmentStorage::DISK);

    expect(PedidoAttachment::query()->whereKey($demoAttachment->id)->exists())->toBeFalse();
    expect($disk->exists($demoAttachment->path))->toBeFalse();
    expect(PedidoAttachment::query()->pluck('id')->all())->toBe([$realAttachment->id]);
    expect($disk->exists($realAttachment->path))->toBeTrue();
    expect($disk->get($realAttachment->path))->toBe(anexoPdfBytes());
});

test('a failure inside the reset transaction deletes no attachment file (RF-43, RNF-02)', function () {
    Storage::fake(PedidoAttachmentStorage::DISK);
    $this->seed(DemoSeeder::class);

    $demoPedido = Pedido::query()->where('is_demo', true)->firstOrFail();
    $demoUser = User::query()->where('email', 'obra.demo@example.com')->firstOrFail();
    $demoAttachment = resetDemoAttachment($demoPedido, $demoUser);

    // A demo user acting on a real pedido blocks the reset through the
    // `pedido_events.actor_id` restrict FK, after the demo pedidos went.
    $realPedido = Pedido::factory()->create([
        'status_id' => Status::query()->where('slug', StatusSlug::Solicitado->value)->value('id'),
        'is_demo' => false,
    ]);
    $realPedido->events()->create([
        'event_type_id' => EventType::query()->where('slug', EventTypeSlug::CriacaoPedido->value)->value('id'),
        'actor_id' => $demoUser->id,
    ]);

    expect(fn () => Artisan::call('demo:reset', ['--force' => true]))->toThrow(QueryException::class);

    expect(PedidoAttachment::query()->whereKey($demoAttachment->id)->exists())->toBeTrue();
    expect(Pedido::query()->whereKey($demoPedido->id)->exists())->toBeTrue();
    expect(Storage::disk(PedidoAttachmentStorage::DISK)->exists($demoAttachment->path))->toBeTrue();
});
