<?php

use App\Domain\Pedidos\AtrasoClassifier;
use App\Domain\Pedidos\PrazoClassifier;
use App\Models\Pedido;
use App\Models\Status;
use Illuminate\Support\Carbon;

/**
 * RF-45 / CT-08: "today" for atraso and prazo is the `America/Sao_Paulo`
 * calendar day, in PHP and in SQL alike, while storage and
 * `config('app.timezone')` stay UTC.
 */
beforeEach(function () {
    $this->solicitado = Status::factory()->solicitado()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the application timezone stays UTC', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

test('at 21/09 22:30 local a pedido needed on 21/09 is not atrasado in PHP nor in SQL', function () {
    $pedido = Pedido::factory()->create(['needed_at' => '2026-09-21', 'status_id' => $this->solicitado->id]);

    Carbon::setTestNow(Carbon::parse('2026-09-22T01:30:00Z'));

    expect(AtrasoClassifier::isAtrasado($pedido->fresh()))->toBeFalse()
        ->and(AtrasoClassifier::scopeAtrasado(Pedido::query())->pluck('id')->all())->not->toContain($pedido->id);
});

test('at 22/09 00:30 local a pedido needed on 21/09 is atrasado in PHP and in SQL', function () {
    $pedido = Pedido::factory()->create(['needed_at' => '2026-09-21', 'status_id' => $this->solicitado->id]);

    Carbon::setTestNow(Carbon::parse('2026-09-22T03:30:00Z'));

    expect(AtrasoClassifier::isAtrasado($pedido->fresh()))->toBeTrue()
        ->and(AtrasoClassifier::scopeAtrasado(Pedido::query())->pluck('id')->all())->toContain($pedido->id);
});

test('prazo counts days from the local today: +3 is vencendo_em_breve and +4 is dentro_do_prazo at 22:30 local', function () {
    $vencendo = Pedido::factory()->create(['needed_at' => '2026-09-24', 'status_id' => $this->solicitado->id]);
    $dentro = Pedido::factory()->create(['needed_at' => '2026-09-25', 'status_id' => $this->solicitado->id]);
    $hoje = Pedido::factory()->create(['needed_at' => '2026-09-21', 'status_id' => $this->solicitado->id]);

    Carbon::setTestNow(Carbon::parse('2026-09-22T01:30:00Z'));

    expect(PrazoClassifier::classificar($vencendo->fresh()))->toBe('vencendo_em_breve')
        ->and(PrazoClassifier::classificar($dentro->fresh()))->toBe('dentro_do_prazo')
        ->and(PrazoClassifier::classificar($hoje->fresh()))->toBe('vencendo_em_breve');
});
