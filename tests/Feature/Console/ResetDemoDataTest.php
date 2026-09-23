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
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;

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
