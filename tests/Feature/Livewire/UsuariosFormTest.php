<?php

use App\Actions\Usuarios\SendAccessLinkAction;
use App\Enums\RoleSlug;
use App\Livewire\Gestao\Usuarios\Form;
use App\Models\Obra;
use App\Models\Role;
use App\Models\User;
use App\Notifications\FirstAccessInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->gestao = User::factory()->gestao()->create(['name' => 'Gestora Principal']);
    $this->obraRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Obra->value], ['name' => 'Obra']);
    $this->suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);
    $this->gestaoRole = Role::query()->where('slug', RoleSlug::Gestao->value)->firstOrFail();
});

test('the create and edit pages render for gestao without any password (RF-25)', function () {
    $this->actingAs($this->gestao);

    $target = User::factory()->obra()->create(['name' => 'Ana Obra', 'email' => 'ana@example.com']);

    $this->get(route('gestao.usuarios.create'))
        ->assertOk()
        ->assertSee('Novo usuário')
        ->assertDontSee('$2y$', false)
        ->assertDontSee('password', false);

    $html = $this->get(route('gestao.usuarios.edit', $target))
        ->assertOk()
        ->assertSee('Editar usuário')
        ->assertSee('Ana Obra')
        ->assertSee('ana@example.com')
        ->assertDontSee('$2y$', false)
        ->getContent();

    expect($html)->not->toContain($target->password)->not->toContain($this->gestao->password);
});

test('gestao creates an obra user with obras through the form (TC-04, TC-05)', function () {
    $this->actingAs($this->gestao);

    $obraA = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $obraB = Obra::factory()->create(['name' => 'Comercial Bravo']);

    Livewire::test(Form::class)
        ->set('name', 'Ana Nova')
        ->set('email', 'ana.nova@example.com')
        ->set('roleId', $this->obraRole->id)
        ->assertSee('Residencial Aurora')
        ->assertSee('Comercial Bravo')
        ->set('obraIds', [(string) $obraA->id, (string) $obraB->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('gestao.usuarios.index'));

    $user = User::query()->where('email', 'ana.nova@example.com')->firstOrFail();

    expect($user->name)->toBe('Ana Nova');
    expect($user->role_id)->toBe($this->obraRole->id);
    expect($user->is_active)->toBeTrue();
    expect($user->is_demo)->toBeFalse();
    expect($user->obras()->pluck('obras.id')->sort()->values()->all())->toBe([$obraA->id, $obraB->id]);
    expect(Hash::check('password', $user->password))->toBeFalse();
    expect(session('status'))->toBe('Usuário criado. Convite enviado para ana.nova@example.com.');
});

test('creating a user sends exactly one first-access invite to that user after the commit (TC-16, RF-29)', function () {
    $this->actingAs($this->gestao);

    Livewire::test(Form::class)
        ->set('name', 'Convidada Nova')
        ->set('email', 'convidada@example.com')
        ->set('roleId', $this->suprimentosRole->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('gestao.usuarios.index'));

    $user = User::query()->where('email', 'convidada@example.com')->firstOrFail();

    Notification::assertSentTo($user, FirstAccessInvite::class);
    Notification::assertCount(1);
    Notification::assertNotSentTo($this->gestao, FirstAccessInvite::class);

    expect(DB::table('password_reset_tokens')->where('email', 'convidada@example.com')->exists())->toBeTrue();
    expect(session('status'))->toBe('Usuário criado. Convite enviado para convidada@example.com.');
});

test('when the invite dispatch fails the user is kept, the failure is reported and the feedback is honest (TC-19, RF-29)', function () {
    $this->actingAs($this->gestao);
    Exceptions::fake();

    $this->mock(SendAccessLinkAction::class, function ($mock): void {
        $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('Transporte de e-mail indisponível'));
    });

    Livewire::test(Form::class)
        ->set('name', 'Sem Convite')
        ->set('email', 'sem.convite@example.com')
        ->set('roleId', $this->suprimentosRole->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('gestao.usuarios.index'));

    $user = User::query()->where('email', 'sem.convite@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
    expect(session('status'))->toBe('Usuário criado, mas o convite não pôde ser enviado — use Reenviar convite.');

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Transporte de e-mail indisponível');
    Notification::assertNothingSent();

    $this->get(route('gestao.usuarios.index'))
        ->assertOk()
        ->assertSee('Usuário criado, mas o convite não pôde ser enviado — use Reenviar convite.')
        ->assertSee('Reenviar convite');
});

test('a validation failure or a rolled-back insert never sends an invite (RF-29)', function () {
    $this->actingAs($this->gestao);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('email', 'invalido')
        ->set('roleId', $this->suprimentosRole->id)
        ->call('save')
        ->assertHasErrors(['name', 'email']);

    Notification::assertNothingSent();

    User::created(function (): void {
        throw new RuntimeException('Falha simulada dentro da transação');
    });

    expect(fn () => Livewire::test(Form::class)
        ->set('name', 'Revertida')
        ->set('email', 'revertida@example.com')
        ->set('roleId', $this->suprimentosRole->id)
        ->call('save'))->toThrow(RuntimeException::class);

    expect(User::query()->where('email', 'revertida@example.com')->exists())->toBeFalse();
    expect(DB::table('password_reset_tokens')->where('email', 'revertida@example.com')->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('gestao creates a suprimentos user without any obra association', function () {
    $this->actingAs($this->gestao);

    Obra::factory()->create(['name' => 'Residencial Aurora']);

    Livewire::test(Form::class)
        ->set('name', 'Bruno Compras')
        ->set('email', 'bruno@example.com')
        ->set('roleId', $this->suprimentosRole->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('gestao.usuarios.index'));

    $user = User::query()->where('email', 'bruno@example.com')->firstOrFail();

    expect($user->role_id)->toBe($this->suprimentosRole->id);
    expect(DB::table('obra_profile')->where('user_id', $user->id)->count())->toBe(0);
});

test('the obra selector is only rendered while the selected perfil is obra (RF-09)', function () {
    $this->actingAs($this->gestao);

    Obra::factory()->create(['name' => 'Residencial Aurora']);

    Livewire::test(Form::class)
        ->assertDontSee('Residencial Aurora')
        ->assertDontSeeHtml('data-obra-selector')
        ->set('roleId', $this->suprimentosRole->id)
        ->assertDontSee('Residencial Aurora')
        ->assertDontSeeHtml('data-obra-selector')
        ->set('roleId', $this->gestaoRole->id)
        ->assertDontSeeHtml('data-obra-selector')
        ->set('roleId', $this->obraRole->id)
        ->assertSee('Residencial Aurora')
        ->assertSeeHtml('data-obra-selector');
});

test('validation failures show PT-BR messages per field and persist nothing (RF-07)', function () {
    $this->actingAs($this->gestao);

    User::factory()->suprimentos()->create(['email' => 'existente@example.com']);
    $before = User::query()->count();

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('email', 'nao-e-email')
        ->set('roleId', null)
        ->call('save')
        ->assertHasErrors(['name', 'email', 'roleId'])
        ->assertSee('Informe o nome.')
        ->assertSee('Informe um e-mail válido.')
        ->assertSee('Selecione o perfil.')
        ->assertNoRedirect();

    Livewire::test(Form::class)
        ->set('name', 'Duplicado')
        ->set('email', 'existente@example.com')
        ->set('roleId', $this->suprimentosRole->id)
        ->call('save')
        ->assertHasErrors(['email'])
        ->assertSee('Já existe um usuário com este e-mail.')
        ->assertNoRedirect();

    Livewire::test(Form::class)
        ->set('name', 'Sem Obra')
        ->set('email', 'sem.obra@example.com')
        ->set('roleId', $this->obraRole->id)
        ->set('obraIds', [])
        ->call('save')
        ->assertHasErrors(['obraIds'])
        ->assertSee('Selecione pelo menos uma obra para o perfil Obra.')
        ->assertNoRedirect();

    expect(User::query()->count())->toBe($before);
});

test('gestao edits nome, e-mail, perfil and obras of an existing user (TC-06, TC-23)', function () {
    $this->actingAs($this->gestao);

    $obraA = Obra::factory()->create(['name' => 'Obra A']);
    $obraB = Obra::factory()->create(['name' => 'Obra B']);
    $obraC = Obra::factory()->create(['name' => 'Obra C']);

    $target = User::factory()->obra()->create(['name' => 'Ana Antiga', 'email' => 'antiga@example.com']);
    $target->obras()->sync([$obraA->id, $obraB->id]);
    $originalHash = $target->password;

    Livewire::test(Form::class, ['user' => $target])
        ->assertSet('name', 'Ana Antiga')
        ->assertSet('email', 'antiga@example.com')
        ->assertSet('roleId', $this->obraRole->id)
        ->assertSet('obraIds', [$obraA->id, $obraB->id])
        ->set('name', 'Ana Nova')
        ->set('email', 'nova@example.com')
        ->set('obraIds', [(string) $obraB->id, (string) $obraC->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('gestao.usuarios.index'));

    $target->refresh();

    expect($target->name)->toBe('Ana Nova');
    expect($target->email)->toBe('nova@example.com');
    expect($target->obras()->pluck('obras.id')->sort()->values()->all())->toBe([$obraB->id, $obraC->id]);
    expect($target->password)->toBe($originalHash);
    expect(session('status'))->toBe('Usuário Ana Nova atualizado.');

    Livewire::test(Form::class, ['user' => $target])
        ->set('obraIds', [])
        ->call('save')
        ->assertHasErrors(['obraIds'])
        ->assertSee('Selecione pelo menos uma obra para o perfil Obra.');

    expect($target->fresh()->obras()->pluck('obras.id')->sort()->values()->all())->toBe([$obraB->id, $obraC->id]);
});

test('changing the perfil from obra to suprimentos hides the selector and detaches every obra (TC-24)', function () {
    $this->actingAs($this->gestao);

    $obra = Obra::factory()->create(['name' => 'Obra Única']);
    $target = User::factory()->obra()->create();
    $target->obras()->sync([$obra->id]);

    Livewire::test(Form::class, ['user' => $target])
        ->assertSee('Obra Única')
        ->set('roleId', $this->suprimentosRole->id)
        ->assertDontSeeHtml('data-obra-selector')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('gestao.usuarios.index'));

    expect($target->fresh()->role_id)->toBe($this->suprimentosRole->id);
    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(0);
});

test('gestao cannot change the perfil of their own account through the form (RF-30)', function () {
    $this->actingAs($this->gestao);

    Livewire::test(Form::class, ['user' => $this->gestao])
        ->set('roleId', $this->suprimentosRole->id)
        ->call('save')
        ->assertForbidden();

    expect($this->gestao->fresh()->role_id)->toBe($this->gestaoRole->id);
});

test('obra and suprimentos cannot mount the form nor forge save (RF-05)', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $target = User::factory()->obra()->create(['name' => 'Alvo Original']);
    $before = User::query()->count();

    $this->actingAs($actor);

    Livewire::test(Form::class)->assertForbidden();
    Livewire::test(Form::class, ['user' => $target])->assertForbidden();

    $create = Livewire::actingAs($this->gestao)->test(Form::class)
        ->set('name', 'Forjado')
        ->set('email', 'forjado@example.com')
        ->set('roleId', $this->suprimentosRole->id);

    $edit = Livewire::actingAs($this->gestao)->test(Form::class, ['user' => $target])
        ->set('name', 'Alvo Alterado');

    $this->actingAs($actor);

    $create->call('save')->assertForbidden();
    $edit->call('save')->assertForbidden();

    expect(User::query()->count())->toBe($before);
    expect(User::query()->where('email', 'forjado@example.com')->exists())->toBeFalse();
    expect($target->fresh()->name)->toBe('Alvo Original');
})->with(['obra', 'suprimentos']);
