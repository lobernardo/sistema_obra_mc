<?php

namespace Database\Factories;

use App\Enums\EventTypeSlug;
use App\Enums\InternalNotificationEmailStatus;
use App\Enums\StatusSlug;
use App\Models\EventType;
use App\Models\InternalNotification;
use App\Models\Pedido;
use App\Models\PedidoEvent;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pedido, ator and slug are derived from the source event, so a factory
 * notification always matches the event it references (CT-02).
 *
 * @extends Factory<InternalNotification>
 */
class InternalNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory()->gestao(),
            'pedido_event_id' => fn (): int => PedidoEvent::factory()->create([
                'pedido_id' => Pedido::factory()->create([
                    'status_id' => Status::query()->firstOrCreate(
                        ['slug' => StatusSlug::Solicitado->value],
                        Status::factory()->solicitado()->raw(),
                    )->id,
                ])->id,
                'event_type_id' => EventType::query()->firstOrCreate(
                    ['slug' => EventTypeSlug::Observacao->value],
                    ['name' => 'Observação adicionada'],
                )->id,
                'new_value' => 'Observação de teste.',
            ])->id,
            'pedido_id' => fn (array $attributes): int => PedidoEvent::query()->findOrFail($attributes['pedido_event_id'])->pedido_id,
            'actor_id' => fn (array $attributes): int => PedidoEvent::query()->findOrFail($attributes['pedido_event_id'])->actor_id,
            'event_type_slug' => fn (array $attributes): string => PedidoEvent::query()->findOrFail($attributes['pedido_event_id'])->eventType->slug,
            'read_at' => null,
            'email_status' => InternalNotificationEmailStatus::Pendente,
            'email_status_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
