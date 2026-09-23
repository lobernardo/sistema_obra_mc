<?php

use App\Enums\ObraAdminAction;
use App\Enums\ObraStatus;
use App\Livewire\Obras\Form;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\User;
use Livewire\Livewire;

test('the create page renders a status select with exactly the 3 options (UI-04)', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $html = $this->get(route('obras.create'))
        ->assertOk()
        ->assertSee('Nova obra')
        ->assertSee('Responsável')
        ->getContent();

    preg_match('/<select[^>]*id="status".*?<\/select>/s', $html, $select);

    expect($select)->not->toBeEmpty();

    preg_match_all('/<option value="([^"]*)"[^>]*>\s*([^<]*?)\s*<\/option>/', $select[0], $options, PREG_SET_ORDER);

    expect(array_map(fn (array $option) => [$option[1], $option[2]], $options))->toBe([
        ['a_iniciar', 'A iniciar'],
        ['em_andamento', 'Em andamento'],
        ['concluido', 'Concluído'],
    ]);
});

test('the Concluído notice is shown only while Concluído is selected (UI-04)', function () {
    $this->actingAs(User::factory()->suprimentos()->create());

    $notice = 'Obras concluídas deixam de receber novas solicitações. Nenhum pedido, histórico ou associação é excluído.';

    Livewire::test(Form::class)
        ->assertDontSee($notice)
        ->set('status', 'em_andamento')
        ->assertDontSee($notice)
        ->set('status', 'concluido')
        ->assertSee($notice)
        ->set('status', 'a_iniciar')
        ->assertDontSee($notice);
});

test('the edit page of a Concluído obra shows the notice and its current values', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $obra = Obra::factory()->concluida()->create(['name' => 'Obra Encerrada', 'responsavel' => 'Eng. Rui']);

    Livewire::test(Form::class, ['obra' => $obra])
        ->assertSet('name', 'Obra Encerrada')
        ->assertSet('responsavel', 'Eng. Rui')
        ->assertSet('status', 'concluido')
        ->assertSee('Editar obra')
        ->assertSee('Obras concluídas deixam de receber novas solicitações.');
});

test('gestao and suprimentos create an obra through the form (RF-01)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $this->actingAs($actor);

    Livewire::test(Form::class)
        ->set('name', '  Residencial Aurora ')
        ->set('responsavel', 'Eng. Carla')
        ->set('status', 'em_andamento')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('obras.index'));

    $obra = Obra::query()->sole();

    expect($obra->name)->toBe('Residencial Aurora');
    expect($obra->responsavel)->toBe('Eng. Carla');
    expect($obra->status)->toBe(ObraStatus::EmAndamento);
    expect(session('status'))->toBe('Obra Residencial Aurora criada.');

    $event = ObraAdminEvent::query()->sole();

    expect($event->action)->toBe(ObraAdminAction::ObraCreated);
    expect($event->actor_id)->toBe($actor->id);
})->with(['gestao', 'suprimentos']);

test('editing an obra through the form updates it and audits the change (RF-02)', function () {
    $this->actingAs(User::factory()->gestao()->create());

    $obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Norte']);

    Livewire::test(Form::class, ['obra' => $obra])
        ->set('status', 'concluido')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('obras.index'));

    expect($obra->fresh()->status)->toBe(ObraStatus::Concluido);
    expect(ObraAdminEvent::query()->sole()->after)->toBe(['status' => 'concluido']);
    expect(session('status'))->toBe('Obra Obra Norte atualizada.');
});

test('Action validation errors land on the matching inputs', function () {
    $this->actingAs(User::factory()->gestao()->create());

    Obra::factory()->create(['name' => 'Obra Centro']);

    Livewire::test(Form::class)
        ->set('name', ' obra centro')
        ->set('responsavel', str_repeat('r', 256))
        ->set('status', 'em_andamento')
        ->call('save')
        ->assertHasErrors(['responsavel'])
        ->assertNoRedirect();

    Livewire::test(Form::class)
        ->set('name', ' obra centro')
        ->set('status', 'em_andamento')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertSee('Já existe uma obra com este nome.')
        ->assertNoRedirect();

    Livewire::test(Form::class)
        ->set('name', 'Obra Nova')
        ->set('status', 'pausada')
        ->call('save')
        ->assertHasErrors(['status'])
        ->assertNoRedirect();

    expect(Obra::query()->count())->toBe(1);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});

test('an obra user can mount neither the create nor the edit form', function () {
    $this->actingAs(User::factory()->obra()->create());

    Livewire::test(Form::class)->assertForbidden();
    Livewire::test(Form::class, ['obra' => Obra::factory()->create()])->assertForbidden();
});

test('a save forged by an obra user is forbidden and writes nothing (RF-07)', function () {
    $gestao = User::factory()->gestao()->create();
    $obraUser = User::factory()->obra()->create();
    $target = Obra::factory()->emAndamento()->create(['name' => 'Obra Original']);
    $before = Obra::query()->count();

    $create = Livewire::actingAs($gestao)->test(Form::class)
        ->set('name', 'Obra Forjada')
        ->set('status', 'a_iniciar');

    $edit = Livewire::actingAs($gestao)->test(Form::class, ['obra' => $target])
        ->set('name', 'Obra Alterada')
        ->set('status', 'concluido');

    $this->actingAs($obraUser);

    $create->call('save')->assertForbidden();
    $edit->call('save')->assertForbidden();

    expect(Obra::query()->count())->toBe($before);
    expect(Obra::query()->where('name', 'Obra Forjada')->exists())->toBeFalse();
    expect($target->fresh()->name)->toBe('Obra Original');
    expect($target->fresh()->status)->toBe(ObraStatus::EmAndamento);
    expect(ObraAdminEvent::query()->count())->toBe(0);
});

test('the form component exposes no delete method (RF-06)', function () {
    $methods = array_map(fn (ReflectionMethod $method) => strtolower($method->getName()), (new ReflectionClass(Form::class))->getMethods(ReflectionMethod::IS_PUBLIC));

    expect(array_filter($methods, fn (string $name) => preg_match('/delete|destroy|remove|excluir/', $name) === 1))->toBe([]);
});
