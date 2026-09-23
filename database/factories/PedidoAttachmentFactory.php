<?php

namespace Database\Factories;

use App\Enums\PedidoAttachmentKind;
use App\Models\Pedido;
use App\Models\PedidoAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PedidoAttachment>
 */
class PedidoAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'kind' => PedidoAttachmentKind::Anexo,
            'path' => bin2hex(random_bytes(20)),
            'original_name' => 'documento.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'uploaded_by' => User::factory()->obra(),
        ];
    }

    public function romaneio(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => PedidoAttachmentKind::Romaneio,
            'uploaded_by' => User::factory()->suprimentos(),
        ]);
    }
}
