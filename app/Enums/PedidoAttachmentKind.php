<?php

namespace App\Enums;

/**
 * Explicit classification of a pedido attachment (CT-03, RF-30): set by the
 * upload path, never derived from the file name or content.
 */
enum PedidoAttachmentKind: string
{
    case Anexo = 'anexo';
    case Romaneio = 'romaneio';

    public function label(): string
    {
        return match ($this) {
            self::Anexo => 'Anexo',
            self::Romaneio => 'Romaneio',
        };
    }

    /**
     * Allow-list of the kind (RF-14, RF-30, NC-03): canonical extension →
     * the MIME type detected from the file bytes. Both the extension and
     * the detected type must be in this list and match each other.
     *
     * @return array<string, string>
     */
    public function allowedTypes(): array
    {
        $images = [
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        return match ($this) {
            self::Anexo => [
                ...$images,
                'webp' => 'image/webp',
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            self::Romaneio => [
                'pdf' => 'application/pdf',
                ...$images,
            ],
        };
    }

    /**
     * The allowed types as shown to the user, e.g. "PDF, JPG ou PNG".
     */
    public function allowedTypesLabel(): string
    {
        $extensions = array_map('strtoupper', array_keys($this->allowedTypes()));
        $last = array_pop($extensions);

        return implode(', ', $extensions).' ou '.$last;
    }
}
