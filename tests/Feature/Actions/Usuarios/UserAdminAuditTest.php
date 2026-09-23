<?php

use App\Actions\Usuarios\CreateUserAction;
use App\Actions\Usuarios\SendAccessLinkAction;
use App\Actions\Usuarios\SetUserActiveAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\RoleSlug;
use App\Enums\UserAdminAction;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAdminEvent;
use App\Notifications\FirstAccessInvite;
use App\Services\UserAdminAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();

    $this->actor = User::factory()->gestao()->create();
    $this->obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);
    $this->suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);
    $this->gestaoRole = Role::query()->where('slug', RoleSlug::Gestao->value)->firstOrFail();

    $this->create = app(CreateUserAction::class);
    $this->update = app(UpdateUserAction::class);
    $this->setActive = app(SetUserActiveAction::class);
    $this->sendLink = app(SendAccessLinkAction::class);
});

/**
 * @return list<string>
 */
function auditSlugsFor(User $target): array
{
    return UserAdminEvent::query()
        ->where('target_id', $target->id)
        ->orderBy('id')
        ->pluck('action')
        ->map(fn (UserAdminAction $action): string => $action->value)
        ->all();
}

function auditRow(User $target, UserAdminAction $action): UserAdminEvent
{
    return UserAdminEvent::query()
        ->where('target_id', $target->id)
        ->where('action', $action->value)
        ->sole();
}

/**
 * @return array{name: string, email: string, role_id: int, obra_ids?: list<int>}
 */
function unchangedPayloadFor(User $target): array
{
    $payload = [
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $target->role_id,
    ];

    if ($target->role->slug === RoleSlug::Obra->value) {
        $payload['obra_ids'] = $target->obras()->pluck('obras.id')->all();
    }

    return $payload;
}

describe('RF-20 matrix — one record per changed aspect', function () {
    test('user_created: before null, after the 5 whitelisted keys, actor and target ids (AC-F19, AC-F20)', function () {
        [$obraB, $obraA] = Obra::factory()->count(2)->create();

        $result = $this->create->execute($this->actor, [
            'name' => 'Maria Obra',
            'email' => 'maria@example.com',
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obraB->id, $obraA->id],
        ]);

        $user = $result['user'];

        expect(auditSlugsFor($user))->toBe(['user_created', 'access_link_sent']);

        $row = auditRow($user, UserAdminAction::UserCreated);

        expect($row->actor_id)->toBe($this->actor->id);
        expect($row->target_id)->toBe($user->id);
        expect($row->before)->toBeNull();
        expect(array_keys($row->after))->toBe(['name', 'email', 'role', 'is_active', 'obra_ids']);
        expect($row->after)->toBe([
            'name' => 'Maria Obra',
            'email' => 'maria@example.com',
            'role' => 'obra',
            'is_active' => true,
            'obra_ids' => collect([$obraA->id, $obraB->id])->sort()->values()->all(),
        ]);
        expect($row->created_at)->not->toBeNull();
    });

    test('user_updated carries only the changed keys among name and email', function () {
        $target = User::factory()->suprimentos()->create(['name' => 'Antigo', 'email' => 'antigo@example.com']);

        $this->update->execute($this->actor, $target, [
            'name' => 'Novo Nome',
            'email' => 'antigo@example.com',
            'role_id' => $this->suprimentosRole->id,
        ]);

        expect(auditSlugsFor($target))->toBe(['user_updated']);

        $row = auditRow($target, UserAdminAction::UserUpdated);

        expect($row->actor_id)->toBe($this->actor->id);
        expect($row->before)->toBe(['name' => 'Antigo']);
        expect($row->after)->toBe(['name' => 'Novo Nome']);
    });

    test('user_updated with both name and email changed carries both keys', function () {
        $target = User::factory()->suprimentos()->create(['name' => 'Antigo', 'email' => 'antigo@example.com']);

        $this->update->execute($this->actor, $target, [
            'name' => 'Novo',
            'email' => 'novo@example.com',
            'role_id' => $this->suprimentosRole->id,
        ]);

        $row = auditRow($target, UserAdminAction::UserUpdated);

        expect($row->before)->toBe(['name' => 'Antigo', 'email' => 'antigo@example.com']);
        expect($row->after)->toBe(['name' => 'Novo', 'email' => 'novo@example.com']);
    });

    test('role_changed carries the role slug before and after, never role_id', function () {
        $target = User::factory()->suprimentos()->create();

        $this->update->execute($this->actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $this->gestaoRole->id,
        ]);

        expect(auditSlugsFor($target))->toBe(['role_changed']);

        $row = auditRow($target, UserAdminAction::RoleChanged);

        expect($row->before)->toBe(['role' => 'suprimentos']);
        expect($row->after)->toBe(['role' => 'gestao']);
    });

    test('obra_access_changed carries the sorted obra_ids set before and after', function () {
        [$obraA, $obraB, $obraC] = Obra::factory()->count(3)->create();
        $target = User::factory()->obra()->create();
        $target->obras()->sync([$obraC->id, $obraA->id]);

        $this->update->execute($this->actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obraB->id, $obraA->id],
        ]);

        expect(auditSlugsFor($target))->toBe(['obra_access_changed']);

        $row = auditRow($target, UserAdminAction::ObraAccessChanged);

        expect($row->before)->toBe(['obra_ids' => collect([$obraA->id, $obraC->id])->sort()->values()->all()]);
        expect($row->after)->toBe(['obra_ids' => collect([$obraA->id, $obraB->id])->sort()->values()->all()]);
    });

    test('moving to the gestao papel emits role_changed and obra_access_changed (detach) in one call (RF-11b)', function () {
        $obra = Obra::factory()->create();
        $target = User::factory()->obra()->create();
        $target->obras()->sync([$obra->id]);

        $this->update->execute($this->actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $this->gestaoRole->id,
        ]);

        expect(auditSlugsFor($target))->toBe(['role_changed', 'obra_access_changed']);

        $row = auditRow($target, UserAdminAction::ObraAccessChanged);

        expect($row->before)->toBe(['obra_ids' => [$obra->id]]);
        expect($row->after)->toBe(['obra_ids' => []]);
    });

    test('moving from obra to suprimentos emits only role_changed and keeps the associations (RF-11b)', function () {
        $obra = Obra::factory()->create();
        $target = User::factory()->obra()->create();
        $target->obras()->sync([$obra->id]);

        $this->update->execute($this->actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $this->suprimentosRole->id,
        ]);

        expect(auditSlugsFor($target))->toBe(['role_changed']);
        expect($target->fresh()->obras()->pluck('obras.id')->all())->toBe([$obra->id]);
    });

    test('changing name, papel and obras in one UpdateUserAction call emits exactly 3 rows with correct actor and target', function () {
        [$obraA, $obraB] = Obra::factory()->count(2)->create();
        $target = User::factory()->suprimentos()->create(['name' => 'Antes']);

        $this->update->execute($this->actor, $target, [
            'name' => 'Depois',
            'email' => $target->email,
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obraB->id, $obraA->id],
        ]);

        expect(auditSlugsFor($target))->toBe(['user_updated', 'role_changed', 'obra_access_changed']);

        $rows = UserAdminEvent::query()->where('target_id', $target->id)->get();

        expect($rows)->toHaveCount(3);
        expect($rows->pluck('actor_id')->unique()->all())->toBe([$this->actor->id]);
        expect($rows->pluck('target_id')->unique()->all())->toBe([$target->id]);
        expect(UserAdminEvent::query()->count())->toBe(3);
    });

    test('user_deactivated and user_activated carry is_active before and after', function () {
        $target = User::factory()->suprimentos()->create();

        $this->setActive->execute($this->actor, $target, false);

        expect(auditSlugsFor($target))->toBe(['user_deactivated']);

        $row = auditRow($target, UserAdminAction::UserDeactivated);

        expect($row->actor_id)->toBe($this->actor->id);
        expect($row->before)->toBe(['is_active' => true]);
        expect($row->after)->toBe(['is_active' => false]);

        $this->setActive->execute($this->actor, $target->fresh(), true);

        expect(auditSlugsFor($target))->toBe(['user_deactivated', 'user_activated']);

        $row = auditRow($target, UserAdminAction::UserActivated);

        expect($row->before)->toBe(['is_active' => false]);
        expect($row->after)->toBe(['is_active' => true]);
    });

    test('access_link_sent with the default call and access_link_resent with resend: true, both with null before/after (D-03)', function () {
        $target = User::factory()->obra()->create();

        expect($this->sendLink->execute($this->actor, $target))->toBe(Password::RESET_LINK_SENT);

        $this->travel(61)->seconds();

        expect($this->sendLink->execute($this->actor, $target, resend: true))->toBe(Password::RESET_LINK_SENT);

        expect(auditSlugsFor($target))->toBe(['access_link_sent', 'access_link_resent']);

        foreach ([UserAdminAction::AccessLinkSent, UserAdminAction::AccessLinkResent] as $action) {
            $row = auditRow($target, $action);

            expect($row->actor_id)->toBe($this->actor->id);
            expect($row->before)->toBeNull();
            expect($row->after)->toBeNull();
        }
    });
});

describe('RF-20 no-ops emit nothing', function () {
    test('UpdateUserAction with identical data emits no record', function (string $factoryState) {
        $target = User::factory()->{$factoryState}()->create();

        if ($factoryState === 'obra') {
            $target->obras()->sync([Obra::factory()->create()->id]);
        }

        $this->update->execute($this->actor, $target, unchangedPayloadFor($target->fresh()));

        expect(UserAdminEvent::query()->count())->toBe(0);
    })->with(['obra', 'suprimentos']);

    test('SetUserActiveAction with the unchanged value emits no record', function () {
        $active = User::factory()->suprimentos()->create();
        $inactive = User::factory()->suprimentos()->inactive()->create();

        $this->setActive->execute($this->actor, $active, true);
        $this->setActive->execute($this->actor, $inactive, false);

        expect(UserAdminEvent::query()->count())->toBe(0);
    });

    test('SendAccessLinkAction returning RESET_THROTTLED emits no access_link_* record', function () {
        $target = User::factory()->obra()->create();

        expect($this->sendLink->execute($this->actor, $target))->toBe(Password::RESET_LINK_SENT);
        expect($this->sendLink->execute($this->actor, $target, resend: true))->toBe(Password::RESET_THROTTLED);

        expect(auditSlugsFor($target))->toBe(['access_link_sent']);
    });

    test('SendAccessLinkAction returning INVALID_USER emits no access_link_* record', function () {
        $target = User::factory()->obra()->create();
        $target->email = 'fantasma@example.com';

        expect($this->sendLink->execute($this->actor, $target))->toBe(Password::INVALID_USER);
        expect($this->sendLink->execute($this->actor, $target, resend: true))->toBe(Password::INVALID_USER);

        expect(UserAdminEvent::query()->count())->toBe(0);
        Notification::assertNothingSent();
    });
});

describe('RF-21 / RF-22 whitelist and secrets', function () {
    test('every emitted record has before/after keys within the whitelist and user_created.after has exactly the 5 keys', function () {
        [$obraA, $obraB] = Obra::factory()->count(2)->create();

        $created = $this->create->execute($this->actor, [
            'name' => 'Criada',
            'email' => 'criada@example.com',
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obraA->id],
        ])['user'];

        $this->update->execute($this->actor, $created, [
            'name' => 'Renomeada',
            'email' => 'renomeada@example.com',
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obraB->id],
        ]);
        $this->setActive->execute($this->actor, $created->fresh(), false);
        $this->travel(61)->seconds();
        $this->sendLink->execute($this->actor, $created->fresh(), resend: true);

        $rows = UserAdminEvent::query()->get();

        expect($rows->count())->toBeGreaterThanOrEqual(5);

        foreach ($rows as $row) {
            expect(array_diff(array_keys($row->before ?? []), UserAdminAuditRecorder::WHITELIST))->toBe([]);
            expect(array_diff(array_keys($row->after ?? []), UserAdminAuditRecorder::WHITELIST))->toBe([]);
        }

        $createdRow = $rows->firstWhere('action', UserAdminAction::UserCreated);

        expect($createdRow)->not->toBeNull();
        expect(array_keys($createdRow->after))->toEqualCanonicalizing(UserAdminAuditRecorder::WHITELIST);
        expect($createdRow->after)->toHaveCount(5);
    });

    test('the recorder refuses any key outside the whitelist before touching the database', function () {
        $target = User::factory()->obra()->create();
        $recorder = app(UserAdminAuditRecorder::class);

        expect(fn () => $recorder->record($this->actor, $target, UserAdminAction::UserUpdated, ['password' => 'x'], null))
            ->toThrow(LogicException::class);
        expect(fn () => $recorder->record($this->actor, $target, UserAdminAction::UserUpdated, null, ['remember_token' => 'x']))
            ->toThrow(LogicException::class);
        expect(fn () => $recorder->record($this->actor, $target, UserAdminAction::UserUpdated, null, ['name' => 'ok', 'role_id' => 1]))
            ->toThrow(LogicException::class);

        expect(UserAdminEvent::query()->count())->toBe(0);
    });

    test('the snapshot exposes only the whitelisted keys with the role slug and sorted obra_ids', function () {
        [$obraA, $obraB] = Obra::factory()->count(2)->create();
        $target = User::factory()->obra()->create(['name' => 'Alvo', 'email' => 'alvo@example.com']);
        $target->obras()->sync([$obraB->id, $obraA->id]);

        $snapshot = app(UserAdminAuditRecorder::class)->snapshot($target->fresh());

        expect(array_keys($snapshot))->toBe(UserAdminAuditRecorder::WHITELIST);
        expect($snapshot['role'])->toBe('obra');
        expect($snapshot['obra_ids'])->toBe(collect([$obraA->id, $obraB->id])->sort()->values()->all());
        expect($snapshot['is_active'])->toBeTrue();
    });

    test('after the full create + invite flow no audit column holds the password, its hash, the remember_token or the raw token (RF-22)', function () {
        $obra = Obra::factory()->create();

        $user = $this->create->execute($this->actor, [
            'name' => 'Sensível',
            'email' => 'sensivel@example.com',
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obra->id],
        ])['user']->fresh();

        $rawToken = null;
        Notification::assertSentTo($user, FirstAccessInvite::class, function (FirstAccessInvite $notification) use (&$rawToken): bool {
            $rawToken = $notification->token;

            return true;
        });

        $user->forceFill(['remember_token' => 'remember-token-sentinela'])->save();
        $storedHash = $user->password;
        $storedTokenHash = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

        expect($rawToken)->not->toBeNull();
        expect($storedHash)->not->toBeEmpty();
        expect($storedTokenHash)->not->toBeEmpty();

        $rows = DB::table('user_admin_events')->get();

        expect($rows)->not->toBeEmpty();

        $secrets = [$storedHash, $rawToken, $storedTokenHash, 'remember-token-sentinela', session()->getId()];

        foreach ($rows as $row) {
            $serialized = mb_strtolower(json_encode((array) $row));

            foreach ($secrets as $secret) {
                expect(str_contains($serialized, mb_strtolower($secret)))->toBeFalse();
            }
        }
    });
});

describe('RNF-10 atomicity — forced audit failure', function () {
    test('a failing user_created insert rolls back the user and its obra_profile rows', function () {
        UserAdminEvent::creating(function (): void {
            throw new RuntimeException('audit indisponível');
        });

        $obra = Obra::factory()->create();

        expect(fn () => $this->create->execute($this->actor, [
            'name' => 'Nunca Criada',
            'email' => 'nunca@example.com',
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obra->id],
        ]))->toThrow(RuntimeException::class);

        expect(User::query()->where('email', 'nunca@example.com')->exists())->toBeFalse();
        expect(DB::table('obra_profile')->where('obra_id', $obra->id)->exists())->toBeFalse();
        expect(UserAdminEvent::query()->count())->toBe(0);
        Notification::assertNothingSent();
    });

    test('a failing UpdateUserAction audit insert leaves users and obra_profile unchanged', function () {
        [$obraA, $obraB] = Obra::factory()->count(2)->create();
        $target = User::factory()->obra()->create(['name' => 'Antes', 'email' => 'antes@example.com']);
        $target->obras()->sync([$obraA->id]);

        UserAdminEvent::creating(function (): void {
            throw new RuntimeException('audit indisponível');
        });

        expect(fn () => $this->update->execute($this->actor, $target, [
            'name' => 'Depois',
            'email' => 'depois@example.com',
            'role_id' => $this->suprimentosRole->id,
        ]))->toThrow(RuntimeException::class);

        $fresh = $target->fresh();

        expect($fresh->name)->toBe('Antes');
        expect($fresh->email)->toBe('antes@example.com');
        expect($fresh->role_id)->toBe($this->obraRole->id);
        expect($fresh->obras()->pluck('obras.id')->all())->toBe([$obraA->id]);
        expect(UserAdminEvent::query()->count())->toBe(0);
    });

    test('a failing SetUserActiveAction audit insert leaves is_active unchanged', function () {
        $target = User::factory()->suprimentos()->create();

        UserAdminEvent::creating(function (): void {
            throw new RuntimeException('audit indisponível');
        });

        expect(fn () => $this->setActive->execute($this->actor, $target, false))->toThrow(RuntimeException::class);

        expect($target->fresh()->is_active)->toBeTrue();
        expect(UserAdminEvent::query()->count())->toBe(0);
    });

    test('a failing access_link_sent insert inside CreateUserAction keeps the user and user_created, reports and yields invite_sent false (D-03)', function () {
        Exceptions::fake();

        UserAdminEvent::creating(function (UserAdminEvent $event): void {
            if ($event->action === UserAdminAction::AccessLinkSent) {
                throw new RuntimeException('audit de convite indisponível');
            }
        });

        $obra = Obra::factory()->create();

        $result = $this->create->execute($this->actor, [
            'name' => 'Criada Sem Trilha de Convite',
            'email' => 'sem-trilha@example.com',
            'role_id' => $this->obraRole->id,
            'obra_ids' => [$obra->id],
        ]);

        expect($result['invite_sent'])->toBeFalse();
        expect(User::query()->where('email', 'sem-trilha@example.com')->exists())->toBeTrue();
        expect(auditSlugsFor($result['user']))->toBe(['user_created']);
        expect(UserAdminEvent::query()->whereIn('action', ['access_link_sent', 'access_link_resent'])->exists())->toBeFalse();

        Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'audit de convite indisponível');
    });
});
