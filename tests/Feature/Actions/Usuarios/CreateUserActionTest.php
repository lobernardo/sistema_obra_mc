<?php

use App\Actions\Usuarios\CreateUserAction;
use App\Enums\RoleSlug;
use App\Livewire\Auth\LoginForm;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->actor = User::factory()->gestao()->create();
    $this->obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);
    $this->suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);
    $this->gestaoRole = Role::query()->where('slug', RoleSlug::Gestao->value)->firstOrFail();
    $this->action = app(CreateUserAction::class);
});

test('gestao creates an obra user with is_active, not demo, the chosen role and exactly the selected obras (TC-04)', function () {
    [$obraA, $obraB, $obraC] = Obra::factory()->count(3)->create();

    $result = $this->action->execute($this->actor, [
        'name' => 'Maria Obra',
        'email' => 'maria@example.com',
        'role_id' => $this->obraRole->id,
        'obra_ids' => [$obraA->id, $obraB->id],
    ]);

    expect($result['invite_sent'])->toBeTrue();

    $user = $result['user']->fresh();

    expect($user->name)->toBe('Maria Obra');
    expect($user->email)->toBe('maria@example.com');
    expect($user->role_id)->toBe($this->obraRole->id);
    expect($user->is_active)->toBeTrue();
    expect($user->is_demo)->toBeFalse();
    expect($user->obras()->pluck('obras.id')->all())->toEqualCanonicalizing([$obraA->id, $obraB->id]);
    expect($user->obras()->whereKey($obraC->id)->exists())->toBeFalse();
});

test('the generated password is a random hash that never matches the documented default (RF-18, RNF-02)', function () {
    $first = $this->action->execute($this->actor, [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'role_id' => $this->suprimentosRole->id,
    ])['user'];

    $second = $this->action->execute($this->actor, [
        'name' => 'Bia',
        'email' => 'bia@example.com',
        'role_id' => $this->suprimentosRole->id,
    ])['user'];

    expect(Hash::isHashed($first->password))->toBeTrue();
    expect(Hash::check('password', $first->password))->toBeFalse();
    expect($first->password)->not->toBe($second->password);

    Livewire::test(LoginForm::class)
        ->set('email', 'ana@example.com')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

test('suprimentos and gestao users are created without obra associations', function (string $roleProperty) {
    $user = $this->action->execute($this->actor, [
        'name' => 'Carlos',
        'email' => 'carlos@example.com',
        'role_id' => $this->{$roleProperty}->id,
    ])['user'];

    expect($user->obras()->count())->toBe(0);
    expect(DB::table('obra_profile')->where('user_id', $user->id)->count())->toBe(0);
})->with(['suprimentosRole', 'gestaoRole']);

test('an obra user with zero obras is refused and nothing is persisted (TC-23)', function (array $obraIds) {
    $usersBefore = User::query()->count();

    try {
        $this->action->execute($this->actor, [
            'name' => 'Sem Obra',
            'email' => 'semobra@example.com',
            'role_id' => $this->obraRole->id,
            ...$obraIds,
        ]);

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('obra_ids');
        expect($exception->errors()['obra_ids'][0])->toBe('Selecione pelo menos uma obra para o perfil Obra.');
    }

    expect(User::query()->count())->toBe($usersBefore);
    expect(User::query()->where('email', 'semobra@example.com')->exists())->toBeFalse();
})->with([
    'missing key' => [[]],
    'empty array' => [['obra_ids' => []]],
]);

test('obras are prohibited for a non-obra papel', function () {
    $obra = Obra::factory()->create();

    try {
        $this->action->execute($this->actor, [
            'name' => 'Supri',
            'email' => 'supri@example.com',
            'role_id' => $this->suprimentosRole->id,
            'obra_ids' => [$obra->id],
        ]);

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('obra_ids');
    }

    expect(User::query()->where('email', 'supri@example.com')->exists())->toBeFalse();
});

test('duplicate or invalid e-mail, empty name and unknown role produce PT-BR field errors (RF-07)', function (array $payload, string $field, string $message) {
    User::factory()->obra()->create(['email' => 'taken@example.com']);
    $usersBefore = User::query()->count();

    try {
        $this->action->execute($this->actor, [
            'name' => 'Válido',
            'email' => 'valido@example.com',
            'role_id' => $this->suprimentosRole->id,
            ...$payload,
        ]);

        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
        expect($exception->errors()[$field][0])->toBe($message);
    }

    expect(User::query()->count())->toBe($usersBefore);
})->with([
    'duplicate e-mail' => [['email' => 'taken@example.com'], 'email', 'Já existe um usuário com este e-mail.'],
    'invalid e-mail' => [['email' => 'not-an-email'], 'email', 'Informe um e-mail válido.'],
    'empty name' => [['name' => ''], 'name', 'Informe o nome.'],
    'unknown role' => [['role_id' => 999999], 'role_id', 'Perfil inválido.'],
    'non-numeric role' => [['role_id' => 'gestao'], 'role_id', 'Perfil inválido.'],
]);

test('an obra or suprimentos actor is refused and nothing is persisted (RF-05)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $usersBefore = User::query()->count();

    expect(fn () => $this->action->execute($actor, [
        'name' => 'Intruso',
        'email' => 'intruso@example.com',
        'role_id' => $this->suprimentosRole->id,
    ]))->toThrow(AuthorizationException::class);

    expect(User::query()->count())->toBe($usersBefore);
})->with(['obra', 'suprimentos']);
