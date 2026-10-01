<?php

use App\Enums\EventTypeSlug;
use App\Models\Obra;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\User;
use App\Notifications\PedidoEventNotification;
use App\Services\PedidoEventValuePresenter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;

/**
 * T06 — RF-14, RF-15, RNF-06, CT-03: content and subject of the e-mail of
 * an internal notification.
 */
beforeEach(function () {
    config(['app.url' => 'https://exemplo.test']);

    $this->statuses = seedWorkflowStatuses();
    $this->eventTypes = seedHistoryEventTypes();
    $this->actor = User::factory()->suprimentos()->create(['name' => 'Maria Souza']);
    $this->pedido = Pedido::factory()->create([
        'code' => 'PED-000123',
        'obra_id' => Obra::factory()->create(['name' => 'Residencial Aurora'])->id,
        'status_id' => $this->statuses['em_analise']->id,
    ]);
});

/**
 * A history event on the test pedido, created at 2026-09-25 01:30 UTC
 * (24/09/2026 22:30 in São Paulo), with the relations the mailer loads.
 */
function notificationMailEvent(EventTypeSlug $slug, ?string $previous = null, ?string $new = null): PedidoEvent
{
    $event = PedidoEvent::query()->create([
        'pedido_id' => test()->pedido->id,
        'event_type_id' => test()->eventTypes[$slug->value]->id,
        'previous_value' => $previous,
        'new_value' => $new,
        'actor_id' => test()->actor->id,
    ]);

    DB::table('pedido_events')->where('id', $event->id)->update(['created_at' => '2026-09-25 01:30:00']);

    return PedidoEvent::query()->with(['eventType', 'actor', 'pedido.obra'])->findOrFail($event->id);
}

function notificationFor(PedidoEvent $event): PedidoEventNotification
{
    $description = app(PedidoEventValuePresenter::class)->describeEach(collect([$event]))[$event->id];

    return new PedidoEventNotification($event, $description);
}

test('the e-mail carries code, obra, type label, content, actor, local date/time and the link of the recipient papel (RF-14, CT-03)', function () {
    $recipient = User::factory()->obra()->create(['name' => 'Ana Obra']);
    $event = notificationMailEvent(EventTypeSlug::Observacao, new: 'Falta de cimento CP-II no canteiro.');

    $mail = notificationFor($event)->toMail($recipient);

    expect($mail->subject)->toBe('[PED-000123] Observação adicionada — Residencial Aurora');
    expect($mail->markdown)->toBe('mail.pedidos.notificacao');
    expect($mail->actionText)->toBe('Ver pedido');
    expect($mail->actionUrl)->toBe("https://exemplo.test/obra/pedidos/{$this->pedido->id}");

    $html = $mail->render()->toHtml();

    expect($html)
        ->toContain('Olá, Ana Obra!')
        ->toContain('PED-000123')
        ->toContain('Residencial Aurora')
        ->toContain('Observação adicionada')
        ->toContain('Falta de cimento CP-II no canteiro.')
        ->toContain('Maria Souza')
        ->toContain('24/09/2026 22:30')
        ->toContain('Ver pedido')
        ->toContain(e($mail->actionUrl))
        ->toContain('Todos os direitos reservados')
        ->not->toContain('password')
        ->not->toContain('senha')
        ->not->toContain('token');

    foreach (['All rights reserved', 'Regards', 'Hello!', "If you're having trouble"] as $english) {
        expect($html)->not->toContain($english);
    }
});

test('a status change renders "<anterior> → <novo>" and links a gestao recipient to the gestao detail (RF-15)', function () {
    $recipient = User::factory()->gestao()->create();
    $event = notificationMailEvent(
        EventTypeSlug::MudancaStatus,
        (string) $this->statuses['em_analise']->id,
        (string) $this->statuses['aguardando_entrega']->id,
    );

    $mail = notificationFor($event)->toMail($recipient);

    expect($mail->subject)->toBe('[PED-000123] '.$this->eventTypes['mudanca_status']->name.' — Residencial Aurora');
    expect($mail->actionUrl)->toBe("https://exemplo.test/gestao/pedidos/{$this->pedido->id}");
    expect($mail->render()->toHtml())
        ->toContain(e($this->statuses['em_analise']->name.' → '.$this->statuses['aguardando_entrega']->name));
});

test('a pedido "Outra" puts its label in the subject', function () {
    $this->pedido->update(['obra_id' => null, 'obra_reference' => 'Galpão Norte']);
    $event = notificationMailEvent(EventTypeSlug::Observacao, new: 'Troca de material.');

    expect(notificationFor($event)->toMail(User::factory()->suprimentos()->create())->subject)
        ->toBe('[PED-000123] Observação adicionada — Outra — Galpão Norte');
});

test('user-written markdown and HTML are shown as typed, never as a link, heading nor tag', function () {
    $event = notificationMailEvent(EventTypeSlug::Observacao, new: "# Urgente\n[clique aqui](https://malicioso.test) <b>negrito</b> *ênfase*");

    $html = notificationFor($event)->toMail(User::factory()->gestao()->create())->render()->toHtml();

    expect($html)
        ->not->toContain('href="https://malicioso.test"')
        ->not->toContain('<b>negrito</b>')
        ->not->toContain('<em>ênfase</em>')
        ->not->toContain('<h1>Urgente</h1>')
        ->toContain('[clique aqui](https://malicioso.test)')
        ->toContain('&lt;b&gt;negrito&lt;/b&gt;')
        ->toContain('*ênfase*')
        ->toContain('# Urgente');
});

test('the message reaches the transport addressed to the recipient, from config(mail.from)', function () {
    $recipient = User::factory()->suprimentos()->create(['email' => 'suprimentos@example.com']);
    $event = notificationMailEvent(EventTypeSlug::Observacao, new: 'Atraso na entrega.');

    $transport = Mail::mailer()->getSymfonyTransport();
    $transport->flush();

    Notification::send($recipient, notificationFor($event));

    expect($transport->messages())->toHaveCount(1);

    $email = $transport->messages()->first()->getOriginalMessage();

    expect($email)->toBeInstanceOf(Email::class);
    expect($email->getTo()[0]->getAddress())->toBe('suprimentos@example.com');
    expect($email->getFrom()[0]->getAddress())->toBe(config('mail.from.address'));
    expect($email->getSubject())->toBe('[PED-000123] Observação adicionada — Residencial Aurora');
    expect($email->getAttachments())->toBe([]);
});

test('no class in app/Notifications implements ShouldQueue (no worker exists)', function () {
    foreach (File::allFiles(app_path('Notifications')) as $file) {
        $class = 'App\\Notifications\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class)) {
            continue;
        }

        expect(is_subclass_of($class, ShouldQueue::class))->toBeFalse("{$class} implementa ShouldQueue");
    }

    expect(new PedidoEventNotification(new PedidoEvent, ['action' => 'x', 'context' => null, 'at' => '', 'actor' => null]))
        ->not->toBeInstanceOf(ShouldQueue::class);
});
