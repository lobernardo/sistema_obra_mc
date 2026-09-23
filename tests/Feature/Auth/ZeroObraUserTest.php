<?php

use App\Actions\Pedidos\CreatePedidoAction;
use App\Enums\AuthenticationEventType;
use App\Livewire\Auth\LoginForm;
use App\Models\AuthenticationEvent;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * RF-14: an `obra` user with zero associated obras (the state every Novo
 * Cadastro account starts in) authenticates normally, sees nothing and
 * cannot reach any pedido by forgery.
 *
 * N-04: the Nova Solicitação empty state is asserted only through its HTTP
 * route, never through the component class, which slice 2 removes.
 */
beforeEach(function () {
    $this->user = User::factory()->obra()->create([
        'email' => 'sem-obra@example.com',
        'password' => Hash::make('senha-forte-123'),
    ]);

    $this->status = Status::factory()->solicitado()->create();
    $this->obra = Obra::factory()->create();
    $this->pedido = Pedido::factory()->create(['obra_id' => $this->obra->id, 'status_id' => $this->status->id]);
});

test('a zero-obra obra user logs in normally', function () {
    Livewire::test(LoginForm::class)
        ->set('email', 'sem-obra@example.com')
        ->set('password', 'senha-forte-123')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    expect(Auth::id())->toBe($this->user->id);
    expect(AuthenticationEvent::query()->where('user_id', $this->user->id)->where('event', AuthenticationEventType::LoginSuccess)->count())->toBe(1);
});

test('the obra pedidos listing answers 200 with zero rows', function () {
    $html = $this->actingAs($this->user)
        ->get(route('obra.pedidos.index'))
        ->assertOk()
        ->assertSee('Nenhum pedido encontrado para as suas obras.')
        ->assertDontSee($this->pedido->code)
        ->getContent();

    expect(substr_count($html, 'data-pedido-code='))->toBe(0);
});

test('Nova Solicitação shows the empty state (UI-09)', function () {
    $this->actingAs($this->user)
        ->get(route('obra.nova-solicitacao'))
        ->assertOk()
        ->assertSee('Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.');
});

test('any pedido opened by forged id is forbidden', function () {
    $this->actingAs($this->user)->get(route('obra.pedidos.show', $this->pedido))->assertForbidden();
});

test('creating a pedido with a forged obra_selection or "Outra" is refused with 422 on obra_id (RF-07)', function (string $selection) {
    $obraSelection = $selection === 'outra' ? 'outra' : $this->obra->id;

    try {
        app(CreatePedidoAction::class)->execute($this->user, [
            'obra_selection' => $obraSelection,
            'needed_at' => now()->addWeek()->toDateString(),
            'descricao' => 'Cimento',
        ]);

        $this->fail('A ValidationException was expected.');
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe(422);
        expect($exception->errors())->toBe([
            'obra_id' => ['Nenhuma obra ativa está associada ao seu usuário. Fale com a Gestão ou com Suprimentos.'],
        ]);
    }

    expect(Pedido::query()->count())->toBe(1);
})->with(['foreign obra' => 'obra', 'Outra' => 'outra']);
