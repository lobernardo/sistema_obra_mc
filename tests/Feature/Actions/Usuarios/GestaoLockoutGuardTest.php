<?php

use App\Actions\Usuarios\SetUserActiveAction;
use App\Actions\Usuarios\UpdateUserAction;
use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->gestaoRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Gestao->value], ['name' => 'Gestão']);
    $this->suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);
    $this->setActive = new SetUserActiveAction;
    $this->update = new UpdateUserAction;
});

/**
 * @return array{name: string, email: string, role_id: int}
 */
function roleChangePayload(User $target, Role $role): array
{
    return ['name' => $target->name, 'email' => $target->email, 'role_id' => $role->id];
}

function expectLockoutRefusal(Closure $operation, string $message): void
{
    try {
        $operation();

        test()->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['target'][0])->toBe($message);
    }
}

test('gestao cannot deactivate its own account (TC-21)', function () {
    $gestaoA = User::factory()->gestao()->create();
    User::factory()->gestao()->create();

    expectLockoutRefusal(
        fn () => $this->setActive->execute($gestaoA, $gestaoA, false),
        'Você não pode desativar nem alterar o perfil da própria conta.',
    );

    expect($gestaoA->fresh()->is_active)->toBeTrue();
});

test('gestao cannot change the papel of its own account (TC-21)', function () {
    $gestaoA = User::factory()->gestao()->create();
    User::factory()->gestao()->create();

    expectLockoutRefusal(
        fn () => $this->update->execute($gestaoA, $gestaoA, roleChangePayload($gestaoA, $this->suprimentosRole)),
        'Você não pode desativar nem alterar o perfil da própria conta.',
    );

    expect($gestaoA->fresh()->role_id)->toBe($this->gestaoRole->id);
});

test('the last active gestao cannot be deactivated nor have its papel changed (TC-22)', function () {
    $gestaoA = User::factory()->gestao()->create();
    $gestaoB = User::factory()->gestao()->create();

    $this->setActive->execute($gestaoA, $gestaoB, false);
    expect($gestaoB->fresh()->is_active)->toBeFalse();

    expectLockoutRefusal(
        fn () => $this->setActive->execute($gestaoB, $gestaoA, false),
        'É necessário manter pelo menos um usuário Gestão ativo.',
    );
    expect($gestaoA->fresh()->is_active)->toBeTrue();

    expectLockoutRefusal(
        fn () => $this->update->execute($gestaoB, $gestaoA, roleChangePayload($gestaoA, $this->suprimentosRole)),
        'É necessário manter pelo menos um usuário Gestão ativo.',
    );
    expect($gestaoA->fresh()->role_id)->toBe($this->gestaoRole->id);

    $this->setActive->execute($gestaoA, $gestaoB, true);

    $changed = $this->update->execute($gestaoB->fresh(), $gestaoA, roleChangePayload($gestaoA, $this->suprimentosRole));
    expect($changed->role_id)->toBe($this->suprimentosRole->id);

    expectLockoutRefusal(
        fn () => $this->update->execute($gestaoB->fresh(), $gestaoB->fresh(), roleChangePayload($gestaoB, $this->suprimentosRole)),
        'Você não pode desativar nem alterar o perfil da própria conta.',
    );
});

test('a non-gestao target is never blocked by the last-gestao guard', function () {
    $gestao = User::factory()->gestao()->create();
    $obra = User::factory()->obra()->create();

    $updated = $this->setActive->execute($gestao, $obra, false);

    expect($updated->is_active)->toBeFalse();
});

test('changing the papel of an inactive gestao does not count against the last active gestao', function () {
    $gestao = User::factory()->gestao()->create();
    $inactiveGestao = User::factory()->gestao()->inactive()->create();

    $updated = $this->update->execute($gestao, $inactiveGestao, roleChangePayload($inactiveGestao, $this->suprimentosRole));

    expect($updated->role_id)->toBe($this->suprimentosRole->id);
});
