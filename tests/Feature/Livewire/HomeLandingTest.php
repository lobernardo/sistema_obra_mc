<?php

use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * RF-09 / CT-01 (navegacao-sidebar-listagens): `/home` lands every papel on
 * its Pedidos listing; Visão Geral, the Kanbans and the Dashboard keep their
 * routes and stay reachable.
 */
beforeEach(function () {
    seedWorkflowStatuses();
});

test('/home redirects each papel to its Pedidos listing', function (string $role, string $path) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get('/home')->assertRedirect(url($path));
})->with([
    'obra' => ['obra', '/obra/pedidos'],
    'suprimentos' => ['suprimentos', '/suprimentos/pedidos'],
    'gestao' => ['gestao', '/gestao/pedidos'],
]);

test('/home denies a user without a recognised papel with the fixed message', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/home')
        ->assertForbidden()
        ->assertSee('Perfil de acesso não reconhecido.');
});

test('a successful login through LoginForm ends on the papel Pedidos listing with 200', function (string $role, string $routeName) {
    User::factory()->{$role}()->create([
        'email' => "{$role}@example.com",
        'password' => Hash::make('correct-password'),
    ]);

    Livewire::test(LoginForm::class)
        ->set('email', "{$role}@example.com")
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertRedirect(route('home'));

    $this->get(route('home'))->assertRedirect(route($routeName));

    $this->get(route($routeName))->assertOk();
})->with([
    'obra' => ['obra', 'obra.pedidos.index'],
    'suprimentos' => ['suprimentos', 'suprimentos.pedidos.index'],
    'gestao' => ['gestao', 'gestao.pedidos.index'],
]);

test('the former landing screens keep answering 200 to their papel', function (string $role, string $routeName) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->get(route($routeName))->assertOk();
})->with([
    'suprimentos visão geral' => ['suprimentos', 'suprimentos.visao-geral'],
    'suprimentos kanban' => ['suprimentos', 'suprimentos.kanban'],
    'gestao dashboard' => ['gestao', 'gestao.dashboard'],
    'gestao kanban' => ['gestao', 'gestao.kanban'],
]);
