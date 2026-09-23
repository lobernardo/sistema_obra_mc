<?php

use App\Actions\Obras\CreateObraAction;
use App\Actions\Obras\GenerateObraInvitationAction;
use App\Actions\Obras\RevokeObraInvitationAction;
use App\Actions\Obras\UpdateObraAction;
use App\Actions\Usuarios\AttachUserObrasAction;
use App\Actions\Usuarios\DetachUserObraAction;
use App\Enums\ObraStatus;
use App\Enums\RoleSlug;
use App\Livewire\Auth\ObraInvitationPage;
use App\Livewire\Auth\Register;
use App\Models\AccountRegistrationEvent;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/**
 * RF-07, RF-17 — the obra/convite/association surface attacked below the
 * UI: every new Action called directly by an `obra` actor, every new
 * mutating Livewire method replayed by an `obra` user through
 * `/livewire/update`, and forged payloads on the two public account
 * screens.
 */
const OBRAS_AUTHZ_PASSWORD = 'senha-forte-123';

beforeEach(function () {
    Role::factory()->obra()->create();

    $this->gestao = User::factory()->gestao()->create();
    $this->intruder = User::factory()->obra()->create();
    $this->obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Alvo']);
    $this->spareObra = Obra::factory()->emAndamento()->create();
});

/**
 * @return array<string, int>
 */
function obrasAuthzCounts(): array
{
    return [
        'obras' => Obra::query()->count(),
        'obra_invitations' => ObraInvitation::query()->count(),
        'obra_admin_events' => ObraAdminEvent::query()->count(),
        'obra_profile' => DB::table('obra_profile')->count(),
        'user_admin_events' => UserAdminEvent::query()->count(),
        'users' => User::query()->count(),
    ];
}

function obrasAuthzSnapshot(TestResponse $response): string
{
    preg_match('/wire:snapshot="([^"]+)"/', $response->assertOk()->getContent(), $matches);

    expect($matches)->toHaveCount(2, 'No wire:snapshot found on the page.');

    return htmlspecialchars_decode($matches[1], ENT_QUOTES | ENT_SUBSTITUTE);
}

/**
 * @param  array<string, mixed>  $updates
 * @param  list<mixed>  $params
 */
function obrasAuthzLivewireCall(string $snapshot, string $method, array $params = [], array $updates = []): TestResponse
{
    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson('/'.$updateUri, [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => [['method' => $method, 'params' => $params]],
        ]],
    ]);
}

test('every new Action called directly by an obra actor is refused with no row written', function (Closure $call) {
    $target = User::factory()->obra()->create();
    $target->obras()->attach($this->obra->id);
    $invitation = ObraInvitation::factory()->for($this->obra)->create(['created_by' => $this->gestao->id]);
    $before = obrasAuthzCounts();

    expect(fn () => $call($this->intruder, $this->obra, $target, $invitation))->toThrow(AuthorizationException::class);

    expect(obrasAuthzCounts())->toBe($before);
    expect($this->obra->fresh()->name)->toBe('Obra Alvo');
    expect($invitation->fresh()->revoked_at)->toBeNull();
    expect($target->obras()->count())->toBe(1);
})->with([
    'CreateObraAction' => [fn (User $actor) => app(CreateObraAction::class)->execute($actor, ['name' => 'Forjada', 'responsavel' => '', 'status' => ObraStatus::EmAndamento->value])],
    'UpdateObraAction' => [fn (User $actor, Obra $obra) => app(UpdateObraAction::class)->execute($actor, $obra, ['name' => 'Renomeada', 'responsavel' => '', 'status' => ObraStatus::Concluido->value])],
    'GenerateObraInvitationAction' => [fn (User $actor, Obra $obra) => app(GenerateObraInvitationAction::class)->execute($actor, $obra)],
    'RevokeObraInvitationAction' => [fn (User $actor, Obra $obra, User $target, ObraInvitation $invitation) => app(RevokeObraInvitationAction::class)->execute($actor, $invitation)],
    'AttachUserObrasAction' => [fn (User $actor, Obra $obra, User $target) => app(AttachUserObrasAction::class)->execute($actor, $target, ['obra_ids' => [Obra::query()->whereKeyNot($obra->id)->value('id')]])],
    'DetachUserObraAction' => [fn (User $actor, Obra $obra, User $target) => app(DetachUserObraAction::class)->execute($actor, $target, ['obra_id' => $obra->id])],
]);

test('Obras\Form mutating methods replayed by an obra user through /livewire/update are forbidden', function (string $method, Closure $params) {
    $invitation = ObraInvitation::factory()->for($this->obra)->create(['created_by' => $this->gestao->id]);

    $this->actingAs($this->gestao);
    $snapshot = obrasAuthzSnapshot($this->get(route('obras.edit', $this->obra)));

    $this->actingAs($this->intruder);
    $before = obrasAuthzCounts();

    obrasAuthzLivewireCall($snapshot, $method, $params($invitation), ['name' => 'Renomeada'])->assertForbidden();

    expect(obrasAuthzCounts())->toBe($before);
    expect($this->obra->fresh()->name)->toBe('Obra Alvo');
    expect($invitation->fresh()->revoked_at)->toBeNull();
})->with([
    'save' => ['save', fn () => []],
    'generateInvitation' => ['generateInvitation', fn () => []],
    'revokeInvitation' => ['revokeInvitation', fn (ObraInvitation $invitation) => [$invitation->id]],
]);

test('Obras\Form create replayed by an obra user is forbidden', function () {
    $this->actingAs($this->gestao);
    $snapshot = obrasAuthzSnapshot($this->get(route('obras.create')));

    $this->actingAs($this->intruder);
    $before = obrasAuthzCounts();

    obrasAuthzLivewireCall($snapshot, 'save', [], ['name' => 'Forjada', 'status' => ObraStatus::EmAndamento->value])->assertForbidden();

    expect(obrasAuthzCounts())->toBe($before);
});

test('Associacoes\Index mutating methods replayed by an obra user are forbidden', function (string $method, Closure $updates) {
    $target = User::factory()->obra()->create();
    $target->obras()->attach($this->obra->id);
    $other = Obra::factory()->create();

    $this->actingAs($this->gestao);
    $snapshot = obrasAuthzSnapshot($this->get(route('associacoes.index')));

    $this->actingAs($this->intruder);
    $before = obrasAuthzCounts();

    $params = match ($method) {
        'attach' => [$target->id],
        'askRemoval' => [$target->id, $this->obra->id],
        default => [],
    };

    obrasAuthzLivewireCall($snapshot, $method, $params, $updates($target, $this->obra, $other))->assertForbidden();

    expect(obrasAuthzCounts())->toBe($before);
    expect($target->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
})->with([
    'attach' => ['attach', fn (User $target, Obra $obra, Obra $other) => ["selectedObraIds.{$target->id}" => [$other->id]]],
    'askRemoval' => ['askRemoval', fn () => []],
    'confirmRemoval' => ['confirmRemoval', fn (User $target, Obra $obra) => ['confirmingRemoval' => [$target->id, $obra->id]]],
]);

test('forged role_id and obra_ids on Novo Cadastro are never applied (RF-17)', function (string $property, mixed $value) {
    expect(fn () => Livewire::test(Register::class)->set($property, $value))->toThrow(Exception::class);

    $snapshot = obrasAuthzSnapshot($this->get(route('register')));

    $response = obrasAuthzLivewireCall($snapshot, 'register', [], [
        'name' => 'Forjador',
        'email' => 'forjador@example.com',
        'password' => OBRAS_AUTHZ_PASSWORD,
        'password_confirmation' => OBRAS_AUTHZ_PASSWORD,
        $property => $value,
    ]);

    $user = User::query()->where('email', 'forjador@example.com')->first();

    if ($user !== null) {
        expect($user->role->slug)->toBe(RoleSlug::Obra->value);
        expect($user->obras()->count())->toBe(0);
    } else {
        expect($response->status())->not->toBe(200);
    }

    expect(DB::table('obra_profile')->count())->toBe(0);
})->with([
    'role_id' => ['role_id', fn () => Role::query()->where('slug', RoleSlug::Gestao->value)->value('id')],
    'obra_ids' => ['obra_ids', fn () => [Obra::query()->value('id')]],
]);

test('forged role_id and obra_ids on the convite page are never applied (RF-17)', function (string $property, mixed $value) {
    $result = app(GenerateObraInvitationAction::class)->execute($this->gestao, $this->obra);
    $token = parse_url($result['url'], PHP_URL_FRAGMENT);
    $otherObra = Obra::factory()->create();

    expect(fn () => Livewire::test(ObraInvitationPage::class)->call('lookup', $token)->set($property, $value))->toThrow(Exception::class);

    $snapshot = obrasAuthzSnapshot($this->get(route('obra-invitation.show')));
    $lookup = obrasAuthzLivewireCall($snapshot, 'lookup', [$token])->assertOk();

    $response = obrasAuthzLivewireCall($lookup->json('components.0.snapshot'), 'register', [], [
        'name' => 'Forjador',
        'email' => 'forjador@example.com',
        'password' => OBRAS_AUTHZ_PASSWORD,
        'password_confirmation' => OBRAS_AUTHZ_PASSWORD,
        $property => $property === 'obra_ids' ? [$otherObra->id] : $value,
    ]);

    $user = User::query()->where('email', 'forjador@example.com')->first();

    if ($user !== null) {
        expect($user->role->slug)->toBe(RoleSlug::Obra->value);
        expect($user->obras()->pluck('obras.id')->all())->toBe([$this->obra->id]);
    } else {
        expect($response->status())->not->toBe(200);
        expect($result['invitation']->fresh()->used_at)->toBeNull();
    }

    expect(DB::table('obra_profile')->where('obra_id', $otherObra->id)->count())->toBe(0);
})->with([
    'role_id' => ['role_id', fn () => Role::query()->where('slug', RoleSlug::Gestao->value)->value('id')],
    'obra_ids' => ['obra_ids', fn () => [1]],
]);

test('forged invitationId and obraName updates through /livewire/update are refused by #[Locked]', function (string $property) {
    $result = app(GenerateObraInvitationAction::class)->execute($this->gestao, $this->obra);
    $token = parse_url($result['url'], PHP_URL_FRAGMENT);
    $other = app(GenerateObraInvitationAction::class)->execute($this->gestao, Obra::factory()->create(['name' => 'Obra Forjada']))['invitation'];

    $snapshot = obrasAuthzSnapshot($this->get(route('obra-invitation.show')));
    $lookup = obrasAuthzLivewireCall($snapshot, 'lookup', [$token])->assertOk();

    $response = obrasAuthzLivewireCall($lookup->json('components.0.snapshot'), 'register', [], [
        'name' => 'Forjador',
        'email' => 'forjador@example.com',
        'password' => OBRAS_AUTHZ_PASSWORD,
        'password_confirmation' => OBRAS_AUTHZ_PASSWORD,
        $property => $property === 'invitationId' ? $other->id : 'Obra Forjada',
    ]);

    expect($response->status())->not->toBe(200);
    expect(User::query()->where('email', 'forjador@example.com')->exists())->toBeFalse();
    expect($other->fresh()->used_at)->toBeNull();
    expect($result['invitation']->fresh()->used_at)->toBeNull();
    expect(AccountRegistrationEvent::query()->count())->toBe(0);
})->with(['invitationId', 'obraName']);
