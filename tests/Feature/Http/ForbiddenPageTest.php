<?php

use App\Enums\RoleSlug;
use App\Livewire\Gestao\Usuarios\Form;
use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

test('a 403 from a role-gated route renders the PT-BR page with the generic message and Voltar (UI-01, UI-02, UI-03)', function () {
    $this->actingAs(User::factory()->obra()->create());

    $this->get(route('gestao.usuarios.index'))
        ->assertForbidden()
        ->assertSee('Você não tem permissão para acessar esta página.')
        ->assertSee('Voltar')
        ->assertSee('href="'.route('home').'"', false)
        ->assertDontSee('This action is unauthorized.')
        ->assertDontSee('Forbidden');
});

test('the 403 of saving a different perfil on the own account shows the specific message and Voltar (UI-02, UI-03, RF-03)', function () {
    $gestao = User::factory()->gestao()->create();
    $suprimentosRole = Role::query()->firstOrCreate(['slug' => RoleSlug::Suprimentos->value], ['name' => 'Suprimentos']);

    $this->actingAs($gestao);

    Livewire::test(Form::class, ['user' => $gestao])
        ->set('roleId', $suprimentosRole->id)
        ->call('save')
        ->assertForbidden();

    // Livewire's test harness does not expose the rendered error body, so a probe route
    // raises the very same denial through the HTTP kernel and its exception handler.
    Route::middleware(['web', 'auth'])->get('/__forbidden-probe', function () {
        Gate::authorize('changeRole', auth()->user());
    });

    $this->get('/__forbidden-probe')
        ->assertForbidden()
        ->assertSee(UserPolicy::SELF_ROLE_CHANGE_DENIED_MESSAGE)
        ->assertSee('Voltar')
        ->assertSee('href="'.route('home').'"', false)
        ->assertDontSee('This action is unauthorized.')
        ->assertDontSee('Você não tem permissão para acessar esta página.')
        ->assertDontSee('Forbidden');
});

test('the 403 view stays escaped, brand-neutral, sidebar-free and without history navigation (UI-01, UI-03)', function () {
    $contents = file_get_contents(resource_path('views/errors/403.blade.php'));

    expect($contents)
        ->not->toContain('{!!')
        ->not->toContain('<aside')
        ->not->toContain('Albuquerque Engenharia')
        ->not->toContain('history.back')
        ->not->toContain('javascript:')
        ->toContain("config('app.name')")
        ->toContain("route('home')")
        ->toContain('target="_top"');

    expect(preg_match('/class="[^"]*\{\{/', $contents))->toBe(0);
});
