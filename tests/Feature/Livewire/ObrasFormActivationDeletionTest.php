<?php

use App\Enums\ObraAdminAction;
use App\Exceptions\Obras\ObraNotFoundException;
use App\Livewire\Obras\Form;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/**
 * obras-ativacao-exclusao UI-01..UI-04, RF-09, RNF-01 (D-2), CT-04: the
 * Desativar/Reativar buttons, the two-step Excluir, the blocked-deletion
 * reason with its "Desativar obra" shortcut and the "não encontrada"
 * redirect of the edit form.
 */
beforeEach(function () {
    $this->actor = User::factory()->gestao()->create();
    $this->obra = Obra::factory()->emAndamento()->create(['name' => 'Obra Norte']);
});

test('the form holds the edited obra only as the locked obraId', function () {
    Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra])
        ->assertSet('obraId', $this->obra->id)
        ->assertSet('name', 'Obra Norte');

    expect(fn () => Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra])->set('obraId', 999))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('Desativar shows INATIVA and "Obra desativada."; Reativar removes it and shows "Obra reativada." (UI-01, UI-02)', function (string $role) {
    $component = Livewire::actingAs(User::factory()->{$role}()->create())->test(Form::class, ['obra' => $this->obra])
        ->assertSeeHtml('data-testid="deactivate-obra"')
        ->assertDontSeeHtml('data-testid="reactivate-obra"')
        ->assertDontSeeHtml('data-obra-inativa');

    $component->call('deactivate')
        ->assertHasNoErrors()
        ->assertSee('Obra desativada.')
        ->assertSeeHtml('role="status" class="alert-success" data-testid="activity-feedback"')
        ->assertSeeHtml('data-obra-inativa')
        ->assertSee('INATIVA')
        ->assertSeeHtml('data-testid="reactivate-obra"')
        ->assertDontSeeHtml('data-testid="deactivate-obra"');

    expect($this->obra->fresh()->isActive())->toBeFalse();

    $component->call('reactivate')
        ->assertHasNoErrors()
        ->assertSee('Obra reativada.')
        ->assertDontSee('Obra desativada.')
        ->assertDontSeeHtml('data-obra-inativa')
        ->assertSeeHtml('data-testid="deactivate-obra"');

    expect($this->obra->fresh()->isActive())->toBeTrue();
    expect(ObraAdminEvent::query()->pluck('action')->all())
        ->toBe([ObraAdminAction::ObraDeactivated, ObraAdminAction::ObraReactivated]);
})->with(['gestao', 'suprimentos']);

test('confirmDelete alone deletes nothing and cancelDelete restores the initial state (UI-03)', function () {
    $component = Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra])
        ->assertDontSeeHtml('data-testid="delete-confirm-dialog"')
        ->call('confirmDelete')
        ->assertSet('confirmingDelete', true)
        ->assertSeeHtml('role="alertdialog"')
        ->assertSee('Excluir definitivamente a obra «Obra Norte»? Esta ação não pode ser desfeita.')
        ->assertSeeHtml('data-testid="delete-confirm"')
        ->assertSee('Confirmar exclusão')
        ->assertSeeHtml('data-testid="delete-cancel"')
        ->assertNoRedirect();

    expect(Obra::query()->whereKey($this->obra->id)->exists())->toBeTrue();
    expect(ObraAdminEvent::query()->count())->toBe(0);

    $component->call('cancelDelete')
        ->assertSet('confirmingDelete', false)
        ->assertDontSeeHtml('data-testid="delete-confirm-dialog"')
        ->assertSeeHtml('data-testid="delete-obra"')
        ->assertNoRedirect();

    expect($this->obra->fresh())->not->toBeNull();
    expect(ObraAdminEvent::query()->count())->toBe(0);
});

test('"Confirmar exclusão" on an obra without dependencies redirects to /obras with the flash and the row is gone (UI-03)', function () {
    Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra])
        ->call('confirmDelete')
        ->call('deleteObra')
        ->assertHasNoErrors()
        ->assertRedirect(route('obras.index'));

    expect(session('status'))->toBe('Obra «Obra Norte» excluída.');
    expect(Obra::query()->whereKey($this->obra->id)->exists())->toBeFalse();

    $this->actingAs($this->actor)
        ->withSession(['status' => 'Obra «Obra Norte» excluída.'])
        ->get(route('obras.index'))
        ->assertOk()
        ->assertSee('Obra «Obra Norte» excluída.')
        ->assertDontSeeHtml('data-obra-id="'.$this->obra->id.'"');
});

test('a blocked deletion shows the RF-16 reason in role="alert" with a "Desativar obra" that deactivates (UI-04)', function () {
    $solicitadoId = seedWorkflowStatuses()['solicitado']->id;
    Pedido::factory()->count(2)->create(['obra_id' => $this->obra->id, 'status_id' => $solicitadoId]);

    $message = 'Não é possível excluir a obra «Obra Norte»: ela possui 2 pedido(s) e 0 convite(s) utilizado(s). Desative a obra para impedir novos usos.';

    $component = Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra])
        ->call('confirmDelete')
        ->call('deleteObra')
        ->assertHasErrors(['excluir'])
        ->assertNoRedirect()
        ->assertSet('confirmingDelete', false)
        ->assertSeeHtml('role="alert" data-testid="delete-blocked"')
        ->assertSee($message, false)
        ->assertSeeHtml('data-testid="delete-blocked-deactivate"');

    expect($component->errors()->get('excluir'))->toBe([$message]);
    expect(Obra::query()->whereKey($this->obra->id)->exists())->toBeTrue();
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::ObraDeleteBlocked)->count())->toBe(1);

    $component->call('deactivate')
        ->assertSee('Obra desativada.')
        ->assertSeeHtml('data-obra-inativa');

    expect($this->obra->fresh()->isActive())->toBeFalse();
    expect(Pedido::query()->where('obra_id', $this->obra->id)->count())->toBe(2);
});

test('a blocked deletion of an inactive obra offers no "Desativar obra" shortcut (UI-04)', function () {
    $obra = Obra::factory()->inactive()->create(['name' => 'Obra Parada']);
    Pedido::factory()->create(['obra_id' => $obra->id, 'status_id' => seedWorkflowStatuses()['solicitado']->id]);

    Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $obra])
        ->call('deleteObra')
        ->assertHasErrors(['excluir'])
        ->assertSeeHtml('data-testid="delete-blocked"')
        ->assertDontSeeHtml('data-testid="delete-blocked-deactivate"')
        ->assertSeeHtml('data-testid="reactivate-obra"');
});

test('an obra deleted out of band redirects every action to /obras with "A obra informada não foi encontrada." (RNF-01, D-2)', function (string $method) {
    $component = Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra]);

    DB::table('obras')->where('id', $this->obra->id)->delete();

    $component->call($method)
        ->assertStatus(200)
        ->assertRedirect(route('obras.index'));

    expect(session('status'))->toBe('A obra informada não foi encontrada.');
    expect(ObraAdminEvent::query()->count())->toBe(0);
})->with(['deleteObra', 'confirmDelete', 'deactivate', 'reactivate', 'save', 'generateInvitation']);

test('a second concurrent deletion redirects to /obras with the not-found flash and records no second obra_deleted', function () {
    $first = Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra]);
    $second = Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra]);

    $first->call('deleteObra')->assertRedirect(route('obras.index'));

    $second->call('deleteObra')->assertStatus(200)->assertRedirect(route('obras.index'));

    expect(session('status'))->toBe(ObraNotFoundException::MESSAGE);
    expect(ObraAdminEvent::query()->where('action', ObraAdminAction::ObraDeleted)->count())->toBe(1);
});

test('an obra user replaying the activation and deletion methods gets 403 with nothing written (RF-09)', function (string $method) {
    $component = Livewire::actingAs($this->actor)->test(Form::class, ['obra' => $this->obra]);

    $this->actingAs(User::factory()->obra()->create());

    $component->call($method)->assertForbidden();

    expect($this->obra->fresh()->isActive())->toBeTrue();
    expect(Obra::query()->whereKey($this->obra->id)->exists())->toBeTrue();
    expect(ObraAdminEvent::query()->count())->toBe(0);
})->with(['deactivate', 'reactivate', 'confirmDelete', 'deleteObra']);

test('an obra user gets 403 on the edit page (RF-09)', function () {
    $this->actingAs(User::factory()->obra()->create())
        ->get(route('obras.edit', $this->obra))
        ->assertForbidden();
});
