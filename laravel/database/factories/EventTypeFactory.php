<?php

namespace Database\Factories;

use App\Enums\EventTypeSlug;
use App\Models\EventType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventType>
 */
class EventTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->words(3, true));

        return [
            'name' => ucfirst($slug),
            'slug' => $slug,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    private function named(EventTypeSlug $slug, string $name): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'slug' => $slug->value,
        ]);
    }

    public function criacaoPedido(): static
    {
        return $this->named(EventTypeSlug::CriacaoPedido, 'Criação do pedido');
    }

    public function mudancaStatus(): static
    {
        return $this->named(EventTypeSlug::MudancaStatus, 'Mudança de status');
    }

    public function alteracaoResponsavel(): static
    {
        return $this->named(EventTypeSlug::AlteracaoResponsavel, 'Alteração de responsável');
    }

    public function alteracaoPrioridade(): static
    {
        return $this->named(EventTypeSlug::AlteracaoPrioridade, 'Alteração de prioridade');
    }

    public function alteracaoPrevisao(): static
    {
        return $this->named(EventTypeSlug::AlteracaoPrevisao, 'Alteração de previsão');
    }

    public function cancelamento(): static
    {
        return $this->named(EventTypeSlug::Cancelamento, 'Cancelamento');
    }

    public function entrega(): static
    {
        return $this->named(EventTypeSlug::Entrega, 'Entrega');
    }
}
