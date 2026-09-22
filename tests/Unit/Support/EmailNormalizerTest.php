<?php

use App\Services\AuthenticationRateLimiter;
use App\Support\EmailNormalizer;

/**
 * RF-01: `EmailNormalizer::normalize()` is the single canonical e-mail
 * rule — trimmed and lower-cased — and `AuthenticationRateLimiter::
 * normalizeEmail()` must stay output-identical to it, because the four
 * rate limiters key on its result.
 *
 * RF-01's acceptance criterion words its example with the client's own
 * domain; RF-10 forbids that domain in any file this feature adds, so the
 * equivalent `@example.com` address is used throughout. The property under
 * test — mixed case plus surrounding whitespace collapsing to one
 * canonical value — is unchanged.
 */
test('normalize trims surrounding whitespace and lower-cases mixed case (RF-01)', function () {
    expect(EmailNormalizer::normalize('  Marcelo@Example.COM '))->toBe('marcelo@example.com');
});

test('normalize is idempotent on an already canonical address (RF-01)', function () {
    expect(EmailNormalizer::normalize('marcelo@example.com'))->toBe('marcelo@example.com');
    expect(EmailNormalizer::normalize(EmailNormalizer::normalize('  Ana@Example.ORG ')))->toBe('ana@example.org');
});

test('normalize returns an empty string for an empty or whitespace-only input (RF-01)', function (string $input) {
    expect(EmailNormalizer::normalize($input))->toBe('');
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'tab and newline' => ["\t\n"],
]);

test('normalize lower-cases multibyte characters, not only ASCII (RF-01)', function () {
    expect(EmailNormalizer::normalize(' JOSÉ.NIÑO@Example.COM '))->toBe('josé.niño@example.com');
});

test('the rate limiter delegates to the canonical rule with identical output (RF-01)', function (string $input) {
    expect(AuthenticationRateLimiter::normalizeEmail($input))->toBe(EmailNormalizer::normalize($input));
})->with([
    'mixed case with spaces' => ['  Marcelo@Example.COM '],
    'already canonical' => ['marcelo@example.com'],
    'empty' => [''],
    'multibyte' => [' JOSÉ@Example.COM '],
]);
