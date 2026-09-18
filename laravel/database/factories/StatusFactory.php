<?php

namespace Database\Factories;

use App\Enums\StatusSlug;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Status>
 */
class StatusFactory extends Factory
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
            'sort_order' => fake()->unique()->numberBetween(100, 10000),
            'is_active' => true,
        ];
    }

    private function named(StatusSlug $slug, string $name, int $sortOrder): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'slug' => $slug->value,
            'sort_order' => $sortOrder,
        ]);
    }

    public function solicitado(): static
    {
        return $this->named(StatusSlug::Solicitado, 'Solicitado', 1);
    }

    public function emAnalise(): static
    {
        return $this->named(StatusSlug::EmAnalise, 'Em análise', 2);
    }

    public function emCompraPreparacao(): static
    {
        return $this->named(StatusSlug::EmCompraPreparacao, 'Em compra/preparação', 3);
    }

    public function aguardandoEntrega(): static
    {
        return $this->named(StatusSlug::AguardandoEntrega, 'Aguardando entrega', 4);
    }

    public function entregue(): static
    {
        return $this->named(StatusSlug::Entregue, 'Entregue', 5);
    }

    public function cancelado(): static
    {
        return $this->named(StatusSlug::Cancelado, 'Cancelado', 6);
    }
}
