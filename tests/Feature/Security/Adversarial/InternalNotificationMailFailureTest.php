<?php

use App\Enums\EventTypeSlug;
use App\Enums\InternalNotificationEmailStatus;
use App\Models\InternalNotification;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * notificacoes-internas T19 — RF-16, RNF-08, RF-04: a real mutation through
 * `/livewire/update` (full HTTP pipeline, so the deferred e-mail flush runs
 * on `terminate()` like in production) with a transport that refuses
 * recipient A. The mutation stays committed, A is `falhou`, B is `enviado`,
 * and the failure log carries neither an address, the observation text nor
 * a token. A `suprimentos` user neither associated to the obra nor
 * responsible still opens the pedido and is never notified.
 */
const FALHA_EMAIL_OBSERVACAO = 'Troca de fornecedor: o cimento chega só na sexta-feira.';

const FALHA_EMAIL_TOKEN = 'segredo-do-provedor-9f2c';

beforeEach(function () {
    $this->statuses = seedWorkflowStatuses();
    seedHistoryEventTypes();

    $this->obra = Obra::factory()->create();
    $this->actor = User::factory()->suprimentos()->create();
    $this->actor->obras()->attach($this->obra->id);
    $this->outsider = User::factory()->suprimentos()->create();
    $this->recipientA = User::factory()->gestao()->create(['email' => 'gestao.a@example.com']);
    $this->recipientB = User::factory()->gestao()->create(['email' => 'gestao.b@example.com']);

    $this->pedido = Pedido::factory()->create([
        'obra_id' => $this->obra->id,
        'status_id' => $this->statuses['em_analise']->id,
        'responsible_id' => null,
    ]);
});

/**
 * Installs, as the default mailer, a transport that throws for
 * `$failingAddress` — with the address and a token-like value in the
 * exception message — and keeps every other message.
 */
function falhaEmailTransport(string $failingAddress): ArrayObject
{
    $delivered = new ArrayObject;

    Mail::extend('falha-adversarial', fn () => new class($failingAddress, $delivered) extends AbstractTransport
    {
        public function __construct(private string $failingAddress, private ArrayObject $delivered)
        {
            parent::__construct();
        }

        protected function doSend(SentMessage $message): void
        {
            foreach ($message->getEnvelope()->getRecipients() as $recipient) {
                if ($recipient->getAddress() === $this->failingAddress) {
                    throw new TransportException("Recusado para {$this->failingAddress} (token=".FALHA_EMAIL_TOKEN.')');
                }
            }

            $this->delivered[] = $message;
        }

        public function __toString(): string
        {
            return 'falha-adversarial';
        }
    });

    config(['mail.mailers.falha-adversarial' => ['transport' => 'falha-adversarial'], 'mail.default' => 'falha-adversarial']);

    return $delivered;
}

function falhaEmailObservacao(Pedido $pedido, string $texto): TestResponse
{
    $snapshot = null;

    preg_match_all('/wire:snapshot="([^"]+)"/', test()->get(route('suprimentos.pedidos.show', $pedido))->assertOk()->getContent(), $all);

    foreach ($all[1] as $encoded) {
        $decoded = htmlspecialchars_decode($encoded, ENT_QUOTES | ENT_SUBSTITUTE);

        if ((json_decode($decoded, true)['memo']['name'] ?? null) === 'suprimentos.pedido-detalhe') {
            $snapshot = $decoded;
        }
    }

    expect($snapshot)->not->toBeNull('No suprimentos.pedido-detalhe snapshot in the rendered page.');

    Livewire::flushState();

    $updateUri = app('router')->getRoutes()->getByName('default-livewire.update')->uri();

    return test()->withHeaders(['X-Livewire' => 'true'])->postJson('/'.$updateUri, [
        '_token' => csrf_token(),
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => ['observacao' => $texto],
            'calls' => [['method' => 'adicionarObservacao', 'params' => []]],
        ]],
    ]);
}

test('a transport failure for A keeps the mutation, marks A falhou and B enviado, and logs no PII (RF-16, RNF-08)', function () {
    $delivered = falhaEmailTransport('gestao.a@example.com');

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $message) use (&$logged): void {
        $logged[] = $message;
    });

    $this->actingAs($this->actor);
    falhaEmailObservacao($this->pedido, FALHA_EMAIL_OBSERVACAO)->assertOk();

    $event = PedidoEvent::query()
        ->where('pedido_id', $this->pedido->id)
        ->whereHas('eventType', fn ($query) => $query->where('slug', EventTypeSlug::Observacao->value))
        ->sole();

    expect($event->new_value)->toBe(FALHA_EMAIL_OBSERVACAO);

    $notificationA = InternalNotification::query()->where('pedido_event_id', $event->id)->where('recipient_id', $this->recipientA->id)->sole();
    $notificationB = InternalNotification::query()->where('pedido_event_id', $event->id)->where('recipient_id', $this->recipientB->id)->sole();

    expect($notificationA->email_status)->toBe(InternalNotificationEmailStatus::Falhou)
        ->and($notificationA->email_status_at)->not->toBeNull()
        ->and($notificationB->email_status)->toBe(InternalNotificationEmailStatus::Enviado)
        ->and($notificationB->email_status_at)->not->toBeNull();

    $deliveredTo = array_map(fn (SentMessage $message): string => $message->getEnvelope()->getRecipients()[0]->getAddress(), $delivered->getArrayCopy());

    expect($deliveredTo)->toContain('gestao.b@example.com')
        ->not->toContain('gestao.a@example.com')
        ->toHaveCount(InternalNotification::query()->where('pedido_event_id', $event->id)->count() - 1);

    $warnings = array_values(array_filter($logged, fn (MessageLogged $message): bool => $message->level === 'warning'));

    expect($warnings)->toHaveCount(1);

    $serialized = json_encode(array_map(fn (MessageLogged $message): array => [$message->message, $message->context], $logged), JSON_UNESCAPED_UNICODE);

    expect($serialized)
        ->not->toContain('gestao.a@example.com')
        ->not->toContain('gestao.b@example.com')
        ->not->toContain(FALHA_EMAIL_OBSERVACAO)
        ->not->toContain(FALHA_EMAIL_TOKEN)
        ->not->toContain('token');
});

test('a suprimentos user neither associated nor responsible opens the pedido but is never notified (RF-04)', function () {
    Mail::fake();

    $this->actingAs($this->actor);
    falhaEmailObservacao($this->pedido, FALHA_EMAIL_OBSERVACAO)->assertOk();

    expect(InternalNotification::query()->where('recipient_id', $this->recipientA->id)->count())->toBe(1)
        ->and(InternalNotification::query()->where('recipient_id', $this->outsider->id)->count())->toBe(0)
        ->and(InternalNotification::query()->where('recipient_id', $this->actor->id)->count())->toBe(0);

    $this->actingAs($this->outsider)
        ->get(route('suprimentos.pedidos.show', $this->pedido))
        ->assertOk()
        ->assertSee($this->pedido->code);
});
