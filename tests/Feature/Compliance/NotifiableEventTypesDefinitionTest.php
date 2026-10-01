<?php

use App\Domain\Pedidos\NotifiableEventTypes;
use App\Enums\EventTypeSlug;
use Illuminate\Support\Facades\File;

/**
 * T03 — RF-02, RF-09: every `EventTypeSlug` case is classified in the
 * single definition, and no other file in `app/` decides whether an event
 * type notifies.
 */
const NOTIFIABLE_EVENT_TYPES_FILE = 'app/Domain/Pedidos/NotifiableEventTypes.php';

/**
 * @return array<string, string> relative path => contents, for every PHP
 *                               file in `app/` except the single definition
 */
function appPhpFilesExceptNotifiableDefinition(): array
{
    $files = [];

    foreach (File::allFiles(app_path()) as $file) {
        $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());

        if ($file->getExtension() === 'php' && $relative !== NOTIFIABLE_EVENT_TYPES_FILE) {
            $files[$relative] = $file->getContents();
        }
    }

    return $files;
}

test('every EventTypeSlug case is classified in the single definition', function () {
    $classification = (new NotifiableEventTypes)->classification();

    foreach (EventTypeSlug::cases() as $case) {
        expect($classification)->toHaveKey($case->value, message: "EventTypeSlug::{$case->name} não está classificado em NotifiableEventTypes.");
        expect($classification[$case->value])->toBeBool();
    }
});

test('the classification has no entry outside EventTypeSlug', function () {
    expect(array_diff(array_keys((new NotifiableEventTypes)->classification()), array_column(EventTypeSlug::cases(), 'value')))
        ->toBe([]);
});

test('no other file in app declares a notifiable classification', function () {
    foreach (appPhpFilesExceptNotifiableDefinition() as $path => $contents) {
        expect(preg_match('/function\s+(isNotifiable|classification|notifiableEventTypes)\s*\(/i', $contents))
            ->toBe(0, "{$path} declara uma classificação de tipos notificáveis fora de NotifiableEventTypes.");
    }
});

test('no notification file in app lists event type slugs to decide notification', function () {
    foreach (appPhpFilesExceptNotifiableDefinition() as $path => $contents) {
        if (preg_match('/notif/i', basename($path)) !== 1) {
            continue;
        }

        expect(str_contains($contents, 'EventTypeSlug'))
            ->toBeFalse("{$path} referencia EventTypeSlug; a decisão de notificar pertence só a NotifiableEventTypes.");
    }
});
