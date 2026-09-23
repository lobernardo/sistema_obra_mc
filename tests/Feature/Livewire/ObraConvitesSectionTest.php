<?php

use App\Enums\ObraAdminAction;
use App\Enums\ObraInvitationState;
use App\Livewire\Obras\Form;
use App\Models\Obra;
use App\Models\ObraAdminEvent;
use App\Models\ObraInvitation;
use App\Models\User;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

const CONVITE_ONE_TIME_NOTICE = 'Este link vale por 24 horas e não será exibido novamente.';

test('the create form has no Convites section (UI-05)', function () {
    Livewire::actingAs(User::factory()->gestao()->create())
        ->test(Form::class)
        ->assertDontSee('Gerar convite')
        ->assertDontSeeHtml('data-convites-section');
});

test('generating shows the link once with the copy control and the 24 h notice (UI-05, RF-23)', function (string $factoryState) {
    $actor = User::factory()->{$factoryState}()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $component = Livewire::actingAs($actor)
        ->test(Form::class, ['obra' => $obra])
        ->assertSee('Gerar convite')
        ->assertDontSee(CONVITE_ONE_TIME_NOTICE)
        ->call('generateInvitation')
        ->assertHasNoErrors();

    $link = $component->get('generatedLink');

    expect($link)->toMatch('/^'.preg_quote(url('/convite'), '/').'#[0-9a-f]{64}$/');

    $component
        ->assertSeeHtml('value="'.$link.'"')
        ->assertSeeHtml('data-testid="copy-invitation-link"')
        ->assertSeeHtml('navigator.clipboard.writeText')
        ->assertSee(CONVITE_ONE_TIME_NOTICE);

    expect($obra->invitations()->count())->toBe(1);
    expect(ObraAdminEvent::query()->sole()->action)->toBe(ObraAdminAction::InvitationCreated);
})->with(['gestao', 'suprimentos']);

test('a reload never shows the link again and lists the convite as Pendente with Revogar (RF-23, RF-26)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $link = Livewire::actingAs($actor)
        ->test(Form::class, ['obra' => $obra])
        ->call('generateInvitation')
        ->get('generatedLink');
    $token = parse_url($link, PHP_URL_FRAGMENT);

    $reload = Livewire::actingAs($actor)->test(Form::class, ['obra' => $obra]);

    $reload
        ->assertSet('generatedLink', null)
        ->assertDontSee(CONVITE_ONE_TIME_NOTICE)
        ->assertSee('Pendente')
        ->assertSee('Revogar')
        ->assertSee($actor->name);

    expect($reload->html())->not->toContain($token);

    $page = $this->actingAs($actor)->get(route('obras.edit', $obra))->assertOk()->getContent();

    expect($page)->not->toContain($token);
    expect($page)->not->toContain(CONVITE_ONE_TIME_NOTICE);
});

test('any other action clears the generated link', function () {
    $obra = Obra::factory()->emAndamento()->create();

    $component = Livewire::actingAs(User::factory()->gestao()->create())
        ->test(Form::class, ['obra' => $obra])
        ->call('generateInvitation');

    expect($component->get('generatedLink'))->not->toBeNull();

    $invitation = $obra->invitations()->sole();

    $component->call('confirmRevoke', $invitation->id)
        ->assertSet('generatedLink', null)
        ->assertDontSee(CONVITE_ONE_TIME_NOTICE);

    $component->call('generateInvitation')->set('status', 'a_iniciar')
        ->assertSet('generatedLink', null);
});

test('each state gets its label and only Pendente offers Revogar (RF-26)', function () {
    $actor = User::factory()->gestao()->create();
    $obra = Obra::factory()->emAndamento()->create();

    $pending = ObraInvitation::factory()->for($obra)->create();
    $used = ObraInvitation::factory()->for($obra)->used()->create();
    $revoked = ObraInvitation::factory()->for($obra)->revoked()->create();
    $expired = ObraInvitation::factory()->for($obra)->expired()->create();

    $html = Livewire::actingAs($actor)->test(Form::class, ['obra' => $obra])->html();

    $rowOf = function (ObraInvitation $invitation) use ($html): string {
        preg_match('/<tr[^>]*data-invitation-id="'.$invitation->id.'".*?<\/tr>/s', $html, $row);

        return $row[0] ?? '';
    };

    foreach ([
        [$pending, ObraInvitationState::Pendente],
        [$used, ObraInvitationState::Utilizado],
        [$revoked, ObraInvitationState::Revogado],
        [$expired, ObraInvitationState::Expirado],
    ] as [$invitation, $state]) {
        $row = $rowOf($invitation);

        expect($row)->toMatch('/<td data-invitation-state>\s*'.$state->label().'\s*<\/td>/');
        expect(str_contains($row, 'data-testid="revoke-invitation"'))->toBe($state === ObraInvitationState::Pendente);
    }

    expect($rowOf($used))->toContain(e($used->user->name));
    expect($rowOf($used))->toContain(LocalTime::formatDateTime($used->used_at));
    expect($rowOf($revoked))->toContain(e($revoked->revoker->name));
    expect($rowOf($revoked))->toContain(LocalTime::formatDateTime($revoked->revoked_at));
});

test('the convites are listed newest first', function () {
    $obra = Obra::factory()->emAndamento()->create();

    $older = ObraInvitation::factory()->for($obra)->create(['created_at' => now()->subHours(2)]);
    $newer = ObraInvitation::factory()->for($obra)->create(['created_at' => now()->subHour()]);

    Livewire::actingAs(User::factory()->gestao()->create())
        ->test(Form::class, ['obra' => $obra])
        ->assertSeeHtmlInOrder([
            'data-invitation-id="'.$newer->id.'"',
            'data-invitation-id="'.$older->id.'"',
        ]);
});

test('the HTML never contains a token_hash (RF-38)', function () {
    $obra = Obra::factory()->emAndamento()->create();
    ObraInvitation::factory()->for($obra)->create();
    ObraInvitation::factory()->for($obra)->used()->create();
    ObraInvitation::factory()->for($obra)->revoked()->create();

    $component = Livewire::actingAs(User::factory()->gestao()->create())
        ->test(Form::class, ['obra' => $obra])
        ->call('generateInvitation');

    $hashes = ObraInvitation::query()->pluck('token_hash');

    expect($hashes)->toHaveCount(4);

    foreach ($hashes as $hash) {
        expect($component->html())->not->toContain($hash);
    }

    expect($component->html())->not->toContain('token_hash');
});

test('a Concluído obra hides Gerar convite and the Action still refuses a forged call (RF-33)', function () {
    $obra = Obra::factory()->concluida()->create();

    Livewire::actingAs(User::factory()->gestao()->create())
        ->test(Form::class, ['obra' => $obra])
        ->assertDontSee('Gerar convite')
        ->call('generateInvitation')
        ->assertHasErrors(['obra'])
        ->assertSee('Não é possível gerar convite para uma obra concluída.')
        ->assertSet('generatedLink', null);

    expect(ObraInvitation::query()->count())->toBe(0);
});

test('revoking asks for confirmation first, then revokes (RF-25)', function () {
    $actor = User::factory()->suprimentos()->create();
    $obra = Obra::factory()->emAndamento()->create();
    $invitation = ObraInvitation::factory()->for($obra)->create();

    Livewire::actingAs($actor)
        ->test(Form::class, ['obra' => $obra])
        ->assertDontSeeHtml('data-testid="revoke-confirm-dialog"')
        ->call('confirmRevoke', $invitation->id)
        ->assertSeeHtml('data-testid="revoke-confirm-dialog"')
        ->call('abortRevoke')
        ->assertDontSeeHtml('data-testid="revoke-confirm-dialog"')
        ->call('confirmRevoke', $invitation->id)
        ->call('revokeInvitation', $invitation->id)
        ->assertHasNoErrors()
        ->assertSee('Convite revogado.')
        ->assertDontSeeHtml('data-testid="revoke-invitation"');

    $invitation->refresh();

    expect($invitation->state())->toBe(ObraInvitationState::Revogado);
    expect($invitation->revoked_by)->toBe($actor->id);
});

test('revoking a non-pending convite surfaces the 422 message', function () {
    $obra = Obra::factory()->emAndamento()->create();
    $invitation = ObraInvitation::factory()->for($obra)->used()->create();

    Livewire::actingAs(User::factory()->gestao()->create())
        ->test(Form::class, ['obra' => $obra])
        ->call('revokeInvitation', $invitation->id)
        ->assertHasErrors(['invitation'])
        ->assertSee('Somente convites pendentes podem ser revogados.');
});

test('a convite of another obra cannot be revoked through this form', function () {
    $obra = Obra::factory()->emAndamento()->create();
    $foreign = ObraInvitation::factory()->create();

    $component = Livewire::actingAs(User::factory()->gestao()->create())
        ->test(Form::class, ['obra' => $obra]);

    expect(fn () => $component->call('revokeInvitation', $foreign->id))
        ->toThrow(ModelNotFoundException::class);

    expect($foreign->fresh()->revoked_at)->toBeNull();
});

test('generateInvitation and revokeInvitation forged by an obra user are forbidden (RF-07)', function () {
    $gestao = User::factory()->gestao()->create();
    $obraUser = User::factory()->obra()->create();
    $obra = Obra::factory()->emAndamento()->create();
    $invitation = ObraInvitation::factory()->for($obra)->create();

    $generate = Livewire::actingAs($gestao)->test(Form::class, ['obra' => $obra]);
    $revoke = Livewire::actingAs($gestao)->test(Form::class, ['obra' => $obra]);

    $this->actingAs($obraUser);

    $generate->call('generateInvitation')->assertForbidden();
    $revoke->call('revokeInvitation', $invitation->id)->assertForbidden();

    expect(ObraInvitation::query()->count())->toBe(1);
    expect($invitation->fresh()->revoked_at)->toBeNull();
    expect(ObraAdminEvent::query()->count())->toBe(0);
});

/**
 * RF-47 / F-12: every timestamp of the convite list renders in the
 * `America/Sao_Paulo` calendar through `LocalTime`.
 */
test('the convite list renders criado em and expira em in São Paulo local time (RF-47)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25T01:30:00Z'));

    $obra = Obra::factory()->emAndamento()->create();
    $invitation = ObraInvitation::factory()->for($obra)->create();

    $html = Livewire::actingAs(User::factory()->gestao()->create())->test(Form::class, ['obra' => $obra])->html();

    preg_match('/<tr[^>]*data-invitation-id="'.$invitation->id.'".*?<\/tr>/s', $html, $row);

    expect($row[0] ?? '')->toContain('<td>24/09/2026 22:30</td>')
        ->toContain('<td>25/09/2026 22:30</td>')
        ->not->toContain('25/09/2026 01:30');
});

test('the convite list renders revogado em and utilizado em in São Paulo local time (RF-47)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-25T01:30:00Z'));

    $obra = Obra::factory()->emAndamento()->create();
    $used = ObraInvitation::factory()->for($obra)->used()->create();
    $revoked = ObraInvitation::factory()->for($obra)->revoked()->create();

    $html = Livewire::actingAs(User::factory()->gestao()->create())->test(Form::class, ['obra' => $obra])->html();

    foreach ([$used, $revoked] as $invitation) {
        preg_match('/<tr[^>]*data-invitation-id="'.$invitation->id.'".*?<\/tr>/s', $html, $row);

        expect($row[0] ?? '')->toContain('· 24/09/2026 22:30')
            ->not->toContain('25/09/2026 01:30');
    }
});
