<?php

use App\Enums\StatusSlug;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;

beforeEach(function () {
    foreach (StatusSlug::cases() as $slug) {
        Status::factory()->create([
            'slug' => $slug->value,
            'sort_order' => array_search($slug, StatusSlug::cases(), true) + 1,
        ]);
    }
});

test('all suprimentos screens render without error over http', function () {
    $user = User::factory()->suprimentos()->create();
    $pedido = Pedido::factory()->create([
        'status_id' => Status::query()->where('slug', StatusSlug::Solicitado->value)->value('id'),
    ]);

    $this->actingAs($user);

    $this->get(route('suprimentos.pedidos.index'))->assertOk();
    $this->get(route('suprimentos.pedidos.show', $pedido))->assertOk();
    $this->get(route('suprimentos.kanban'))->assertOk();
    $this->get(route('suprimentos.visao-geral'))->assertOk();
});

test('suprimentos screens are unreachable over http for non-suprimentos roles', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user);

    $this->get(route('suprimentos.pedidos.index'))->assertForbidden();
    $this->get(route('suprimentos.kanban'))->assertForbidden();
    $this->get(route('suprimentos.visao-geral'))->assertForbidden();
})->with(['obra', 'gestao']);
