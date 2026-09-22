<?php

use App\Enums\UserAdminAction;
use App\Livewire\Gestao\Usuarios\Index;
use App\Models\Obra;
use App\Models\User;
use App\Models\UserAdminEvent;
use App\Notifications\FirstAccessInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->gestao = User::factory()->gestao()->create(['name' => 'Gestora Principal', 'email' => 'gestora@example.com']);
});

test('the three usuarios routes carry the full authorization stack (CT-01)', function () {
    $expected = ['auth', 'active', 'can:is-gestao', 'can:manage-users'];

    foreach (['gestao.usuarios.index', 'gestao.usuarios.create', 'gestao.usuarios.edit'] as $name) {
        $route = app('router')->getRoutes()->getByName($name);

        expect($route)->not->toBeNull();
        expect(array_values(array_intersect($route->middleware(), $expected)))->toBe($expected);
    }
});

test('gestao reaches the listing with the Usuários nav link and the 5 columns (TC-01)', function () {
    $this->actingAs($this->gestao);

    $this->get(route('gestao.usuarios.index'))
        ->assertOk()
        ->assertSee('Usuários')
        ->assertSee(route('gestao.usuarios.index'))
        ->assertSeeInOrder(['Nome', 'E-mail', 'Perfil', 'Status', 'Obras']);
});

test('the gestao nav branch has exactly 4 items, the 4th being Usuários (UI-08)', function () {
    $this->actingAs($this->gestao);

    $html = $this->get(route('gestao.dashboard'))->assertOk()->getContent();

    preg_match('/<nav aria-label="Navegação principal".*?<\/nav>/s', $html, $nav);

    expect($nav)->not->toBeEmpty();

    preg_match_all('/<a\s/', $nav[0], $links);

    expect($links[0])->toHaveCount(4);
    expect($nav[0])->toContain(route('gestao.dashboard'))
        ->toContain(route('gestao.kanban'))
        ->toContain(route('gestao.pedidos.index'))
        ->toContain(route('gestao.usuarios.index'));
    expect(strrpos($nav[0], 'Usuários'))->toBeGreaterThan(strrpos($nav[0], 'Todos os Pedidos'));
});

test('each row shows nome, e-mail, perfil, status and the obras of obra users only (RF-03)', function () {
    $this->actingAs($this->gestao);

    $obraA = Obra::factory()->create(['name' => 'Residencial Aurora']);
    $obraB = Obra::factory()->create(['name' => 'Comercial Bravo']);

    $obraUser = User::factory()->obra()->create(['name' => 'Ana Obra', 'email' => 'ana@example.com']);
    $obraUser->obras()->sync([$obraA->id, $obraB->id]);

    $suprimentos = User::factory()->suprimentos()->inactive()->create(['name' => 'Bruno Suprimentos', 'email' => 'bruno@example.com']);
    $suprimentos->obras()->sync([$obraA->id]);

    $html = Livewire::test(Index::class)
        ->assertSee('Ana Obra')
        ->assertSee('ana@example.com')
        ->assertSee('Obra')
        ->assertSee('Bruno Suprimentos')
        ->assertSee('bruno@example.com')
        ->assertSee('Suprimentos')
        ->assertSee('Ativo')
        ->assertSee('Inativo')
        ->html();

    preg_match('/<tr[^>]*data-user-email="ana@example.com".*?<\/tr>/s', $html, $anaRow);
    preg_match('/<tr[^>]*data-user-email="bruno@example.com".*?<\/tr>/s', $html, $brunoRow);

    expect($anaRow[0])->toContain('Comercial Bravo, Residencial Aurora')->toContain('Ativo');
    expect($brunoRow[0])->not->toContain('Residencial Aurora')->toContain('Inativo');
});

test('search filters by nome or e-mail, case-insensitively (RF-04)', function () {
    $this->actingAs($this->gestao);

    User::factory()->obra()->create(['name' => 'Ana Silva', 'email' => 'ana.silva@example.com']);
    User::factory()->suprimentos()->create(['name' => 'Bruno Costa', 'email' => 'bruno@example.com']);
    User::factory()->suprimentos()->create(['name' => 'Carlos Lima', 'email' => 'carlos@example.com']);

    Livewire::test(Index::class)
        ->set('search', 'ana')
        ->assertSee('Ana Silva')
        ->assertDontSee('Bruno Costa')
        ->assertDontSee('Carlos Lima')
        ->set('search', 'BRUNO@')
        ->assertSee('Bruno Costa')
        ->assertDontSee('Ana Silva')
        ->assertDontSee('Carlos Lima')
        ->set('search', '')
        ->assertSee('Ana Silva')
        ->assertSee('Bruno Costa')
        ->assertSee('Carlos Lima');
});

test('gestao deactivates and reactivates a user from the listing (RF-10, RF-11)', function () {
    $this->actingAs($this->gestao);

    $target = User::factory()->obra()->create(['name' => 'Joana Alvo']);

    Livewire::test(Index::class)
        ->call('setActive', $target->id, false)
        ->assertHasNoErrors()
        ->assertSee('Usuário Joana Alvo desativado.');

    expect($target->fresh()->is_active)->toBeFalse();
    expect(User::query()->whereKey($target->id)->exists())->toBeTrue();

    Livewire::test(Index::class)
        ->call('setActive', $target->id, true)
        ->assertHasNoErrors()
        ->assertSee('Usuário Joana Alvo ativado.');

    expect($target->fresh()->is_active)->toBeTrue();
});

test('gestao cannot deactivate their own account from the listing (RF-30)', function () {
    $this->actingAs($this->gestao);

    Livewire::test(Index::class)
        ->call('setActive', $this->gestao->id, false)
        ->assertForbidden();

    expect($this->gestao->fresh()->is_active)->toBeTrue();
});

test('the last-active-gestao guard of the action surfaces inline as a PT-BR error (RF-30)', function () {
    /*
     * Only an actor that is not itself an active Gestão can reach the
     * action-layer guard through the component (the policy already blocks
     * self-deactivation), so this drives the deactivation from a Gestão
     * account that is inactive — the middleware layer is out of scope here.
     */
    $inactiveGestao = User::factory()->gestao()->inactive()->create();
    $this->actingAs($inactiveGestao);

    Livewire::test(Index::class)
        ->call('setActive', $this->gestao->id, false)
        ->assertHasErrors('target')
        ->assertSee('É necessário manter pelo menos um usuário Gestão ativo.');

    expect($this->gestao->fresh()->is_active)->toBeTrue();
});

test('each row offers the resend button and gestao re-sends the access link from the listing (RF-14)', function () {
    $this->actingAs($this->gestao);

    $target = User::factory()->obra()->create(['email' => 'alvo@example.com']);

    Livewire::test(Index::class)
        ->assertSee('Reenviar convite / Enviar link de redefinição')
        ->call('sendAccessLink', $target->id)
        ->assertHasNoErrors()
        ->assertSee('Link de acesso enviado para alvo@example.com.');

    Notification::assertSentTo($target, FirstAccessInvite::class);
    Notification::assertCount(1);
    expect(DB::table('password_reset_tokens')->where('email', 'alvo@example.com')->exists())->toBeTrue();
});

test('the resend button records access_link_resent — never access_link_sent — with gestao as actor and the row user as target (RF-19, RF-20, D-03)', function () {
    $this->actingAs($this->gestao);

    $target = User::factory()->obra()->create(['email' => 'alvo@example.com']);

    Livewire::test(Index::class)
        ->call('sendAccessLink', $target->id)
        ->assertHasNoErrors();

    $rows = UserAdminEvent::query()->where('target_id', $target->id)->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->action)->toBe(UserAdminAction::AccessLinkResent);
    expect($rows->first()->actor_id)->toBe($this->gestao->id);
    expect($rows->first()->before)->toBeNull();
    expect($rows->first()->after)->toBeNull();
    expect(UserAdminEvent::query()->where('action', UserAdminAction::AccessLinkSent->value)->exists())->toBeFalse();
});

test('a throttled resend from the listing records no access_link_* row (RF-20)', function () {
    $this->actingAs($this->gestao);

    $target = User::factory()->obra()->create(['email' => 'alvo@example.com']);

    Livewire::test(Index::class)
        ->call('sendAccessLink', $target->id)
        ->call('sendAccessLink', $target->id)
        ->assertSee('Um link já foi enviado para este e-mail há menos de 1 minuto. Aguarde para reenviar.');

    expect(UserAdminEvent::query()->where('target_id', $target->id)->count())->toBe(1);
});

test('a second resend within one minute sends nothing and tells gestao a link was already sent (TC-25, Q-05)', function () {
    $this->actingAs($this->gestao);

    $target = User::factory()->obra()->create(['email' => 'alvo@example.com']);

    Livewire::test(Index::class)
        ->call('sendAccessLink', $target->id)
        ->assertSee('Link de acesso enviado para alvo@example.com.')
        ->call('sendAccessLink', $target->id)
        ->assertHasNoErrors()
        ->assertSee('Um link já foi enviado para este e-mail há menos de 1 minuto. Aguarde para reenviar.')
        ->assertDontSee('Link de acesso enviado para alvo@example.com.');

    Notification::assertSentTimes(FirstAccessInvite::class, 1);
});

test('obra and suprimentos cannot forge sendAccessLink and no link is issued (RF-05)', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $target = User::factory()->obra()->create(['email' => 'alvo@example.com']);

    $component = Livewire::actingAs($this->gestao)->test(Index::class);

    $this->actingAs($actor);

    $component->call('sendAccessLink', $target->id)->assertForbidden();

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->where('email', 'alvo@example.com')->exists())->toBeFalse();
})->with(['obra', 'suprimentos']);

test('obra and suprimentos receive 403 on the routes (TC-02, TC-03)', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $target = User::factory()->obra()->create();

    $this->actingAs($actor);

    $this->get(route('gestao.usuarios.index'))->assertForbidden();
    $this->get(route('gestao.usuarios.create'))->assertForbidden();
    $this->get(route('gestao.usuarios.edit', $target))->assertForbidden();
})->with(['obra', 'suprimentos']);

test('obra and suprimentos cannot mount the component nor forge setActive (RF-05)', function (string $role) {
    $actor = User::factory()->{$role}()->create();
    $target = User::factory()->obra()->create();
    $snapshot = User::query()->orderBy('id')->get(['id', 'is_active', 'updated_at'])->toArray();

    $this->actingAs($actor);

    Livewire::test(Index::class)->assertForbidden();

    $component = Livewire::actingAs($this->gestao)->test(Index::class);

    $this->actingAs($actor);

    $component->call('setActive', $target->id, false)->assertForbidden();

    expect(User::query()->orderBy('id')->get(['id', 'is_active', 'updated_at'])->toArray())->toBe($snapshot);
    expect($target->fresh()->is_active)->toBeTrue();
})->with(['obra', 'suprimentos']);

test('a guest is redirected to the login page (TC-15)', function () {
    $target = User::factory()->obra()->create();

    $this->get(route('gestao.usuarios.index'))->assertRedirect(route('login'));
    $this->get(route('gestao.usuarios.create'))->assertRedirect(route('login'));
    $this->get(route('gestao.usuarios.edit', $target))->assertRedirect(route('login'));
});

test('the rendered listing never contains a password hash (RF-25)', function () {
    $this->actingAs($this->gestao);

    $target = User::factory()->obra()->create();

    $hashes = User::query()->pluck('password')->all();

    expect($hashes)->not->toBeEmpty();

    $html = $this->get(route('gestao.usuarios.index'))->assertOk()->getContent();

    foreach ($hashes as $hash) {
        expect($html)->not->toContain($hash);
    }

    expect($html)->not->toContain('$2y$');

    $componentHtml = Livewire::test(Index::class)->set('search', $target->email)->html();

    expect($componentHtml)->not->toContain('$2y$');
});
