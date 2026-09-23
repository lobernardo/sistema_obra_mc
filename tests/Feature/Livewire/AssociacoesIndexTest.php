<?php

use App\Livewire\Associacoes\Index;
use App\Models\Obra;
use App\Models\User;
use App\Models\UserAdminEvent;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('gestao and suprimentos open the associations screen (CT-03)', function (string $factoryState) {
    $this->actingAs(User::factory()->{$factoryState}()->create())
        ->get(route('associacoes.index'))
        ->assertOk()
        ->assertSee('Associações');
})->with(['gestao', 'suprimentos']);

test('an obra user gets 403 on the screen, a guest is redirected to login (RF-07)', function () {
    $this->actingAs(User::factory()->obra()->create())
        ->get(route('associacoes.index'))
        ->assertForbidden();

    auth()->logout();

    $this->get(route('associacoes.index'))->assertRedirect(route('login'));
});

test('the search is case-insensitive over nome and e-mail and never lists Gestão users (RF-08)', function () {
    $actor = User::factory()->gestao()->create(['name' => 'Ator', 'email' => 'ator@example.com']);
    User::factory()->obra()->create(['name' => 'Maria Souza', 'email' => 'souza@example.com']);
    User::factory()->suprimentos()->create(['name' => 'Joana', 'email' => 'MARIA@example.com']);
    User::factory()->gestao()->create(['name' => 'Maria Gestora', 'email' => 'gestora@example.com']);
    User::factory()->obra()->create(['name' => 'Pedro', 'email' => 'pedro@example.com']);

    $component = Livewire::actingAs($actor)->test(Index::class)->set('search', 'maria');

    $component->assertSee('Maria Souza')
        ->assertSee('MARIA@example.com')
        ->assertDontSee('Maria Gestora')
        ->assertDontSee('Pedro');

    Livewire::actingAs($actor)->test(Index::class)
        ->assertDontSee('Maria Gestora')
        ->assertDontSee('ator@example.com')
        ->assertSee('Pedro');
});

test('typing a search term resets the page', function () {
    $actor = User::factory()->gestao()->create();
    User::factory()->obra()->count(16)->create();

    Livewire::actingAs($actor)->test(Index::class)
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'x')
        ->assertSet('paginators.page', 1);
});

test('each row shows papel, Ativo/Inativo and all associated obras with their status label (RF-08, UI-06)', function () {
    $actor = User::factory()->suprimentos()->create();
    $target = User::factory()->obra()->inactive()->create(['name' => 'Carlos Obra']);
    $obraA = Obra::factory()->emAndamento()->create(['name' => 'Residencial Aurora']);
    $obraB = Obra::factory()->concluida()->create(['name' => 'Edifício Solar']);
    $target->obras()->attach([$obraA->id, $obraB->id]);

    $html = Livewire::actingAs($actor)->test(Index::class)->set('search', 'carlos')->html();

    expect($html)->toContain('Carlos Obra')
        ->toContain('Obra')
        ->toContain('Inativo')
        ->toContain('Residencial Aurora')
        ->toContain($obraA->status->label())
        ->toContain('Edifício Solar')
        ->toContain($obraB->status->label());
    expect(substr_count($html, 'data-associated-obra='))->toBe(2);
});

test('adding 2 obras in one action shows both and writes one audit (RF-09, UI-06)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create(['name' => 'Ana Obra']);
    $obraA = Obra::factory()->create(['name' => 'Obra Alfa']);
    $obraB = Obra::factory()->create(['name' => 'Obra Beta']);

    Livewire::actingAs($actor)->test(Index::class)
        ->set("selectedObraIds.{$target->id}", [(string) $obraA->id, (string) $obraB->id])
        ->call('attach', $target->id)
        ->assertHasNoErrors()
        ->assertSeeHtml('data-associated-obra="'.$obraA->id.'"')
        ->assertSeeHtml('data-associated-obra="'.$obraB->id.'"');

    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(2);
    expect(UserAdminEvent::query()->count())->toBe(1);
});

test('the multi-select only offers obras not yet associated', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $associated = Obra::factory()->create(['name' => 'Obra Associada']);
    $available = Obra::factory()->create(['name' => 'Obra Livre']);
    $target->obras()->attach($associated->id);

    $html = Livewire::actingAs($actor)->test(Index::class)->html();

    expect($html)->toContain('<option value="'.$available->id.'"')
        ->not->toContain('<option value="'.$associated->id.'"');
});

test('an Action error lands inline on the row selector', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();

    Livewire::actingAs($actor)->test(Index::class)
        ->call('attach', $target->id)
        ->assertHasErrors("selectedObraIds.{$target->id}")
        ->assertSee('Selecione pelo menos uma obra.');

    expect(DB::table('obra_profile')->count())->toBe(0);
});

test('the first click on Remover does not call the Action; the confirmation removes the row (RF-13, UI-06)', function () {
    $actor = User::factory()->gestao()->create();
    $target = User::factory()->obra()->create();
    $obra = Obra::factory()->create();
    $target->obras()->attach($obra->id);

    $component = Livewire::actingAs($actor)->test(Index::class)
        ->call('askRemoval', $target->id, $obra->id)
        ->assertSet('confirmingRemoval', [$target->id, $obra->id])
        ->assertSeeHtml('data-testid="removal-confirm-dialog"');

    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(1);
    expect(UserAdminEvent::query()->count())->toBe(0);

    $component->call('cancelRemoval')->assertSet('confirmingRemoval', null);

    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(1);

    $component->call('askRemoval', $target->id, $obra->id)
        ->call('confirmRemoval')
        ->assertHasNoErrors()
        ->assertSet('confirmingRemoval', null)
        ->assertDontSeeHtml('data-associated-obra="'.$obra->id.'"');

    expect(DB::table('obra_profile')->where('user_id', $target->id)->count())->toBe(0);
    expect(UserAdminEvent::query()->count())->toBe(1);
});

test('attach and confirmRemoval forged by an obra user are forbidden and write nothing (RF-07)', function () {
    $gestao = User::factory()->gestao()->create();
    $obraUser = User::factory()->obra()->create();
    $target = User::factory()->obra()->create();
    [$obraA, $obraB] = Obra::factory()->count(2)->create();
    $target->obras()->attach($obraA->id);

    $attach = Livewire::actingAs($gestao)->test(Index::class);
    $remove = Livewire::actingAs($gestao)->test(Index::class)->call('askRemoval', $target->id, $obraA->id);

    $this->actingAs($obraUser);

    $attach->set("selectedObraIds.{$target->id}", [(string) $obraB->id])
        ->call('attach', $target->id)
        ->assertForbidden();
    $remove->call('confirmRemoval')->assertForbidden();

    expect($target->obras()->pluck('obras.id')->all())->toBe([$obraA->id]);
    expect(UserAdminEvent::query()->count())->toBe(0);
});

test('a Suprimentos user sees the self-association notice only on its own row and still adds and removes its own obras (UI-06 v1.3, F-13)', function () {
    $actor = User::factory()->suprimentos()->create(['name' => 'Sueli Suprimentos']);
    User::factory()->suprimentos()->create(['name' => 'Outro Suprimentos']);
    User::factory()->obra()->create(['name' => 'Otávio Obra']);
    $obra = Obra::factory()->create();

    $component = Livewire::actingAs($actor)->test(Index::class);

    $html = $component->html();

    expect(substr_count($html, 'data-self-association-notice'))->toBe(1);
    expect($html)->toContain('Você está editando as suas próprias associações.');

    $ownSection = str($html)->after('data-user-email="'.$actor->email.'"')->before('</section>')->toString();

    expect($ownSection)->toContain('data-self-association-notice')
        ->toContain('Adicionar')
        ->not->toContain('disabled>Adicionar');

    $component->set("selectedObraIds.{$actor->id}", [(string) $obra->id])
        ->call('attach', $actor->id)
        ->assertHasNoErrors();

    expect(DB::table('obra_profile')->where('user_id', $actor->id)->count())->toBe(1);

    $component->call('askRemoval', $actor->id, $obra->id)
        ->call('confirmRemoval')
        ->assertHasNoErrors();

    expect(DB::table('obra_profile')->where('user_id', $actor->id)->count())->toBe(0);
    expect(UserAdminEvent::query()->where('actor_id', $actor->id)->where('target_id', $actor->id)->count())->toBe(2);
});
