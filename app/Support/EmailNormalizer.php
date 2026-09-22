<?php

namespace App\Support;

/**
 * The single canonical e-mail normalization rule of the application
 * (RF-01): trimmed and lower-cased.
 *
 * Every path that stores, looks up or compares a user e-mail reaches this
 * class — the two user Actions and the Gestão form (RF-02), the Gestão
 * bootstrap command (RF-03), the invite/reset consumption (RF-04), the
 * `users:email-case-report` diagnostic (RF-07), the Fase 0 migration
 * (RF-05) and `AuthenticationRateLimiter::normalizeEmail()`, which keeps
 * its public signature and delegates its body here so limiter keys, audit
 * e-mails and credential lookups can never drift apart.
 *
 * The expression lives here and nowhere else: RF-01's acceptance criterion
 * is a static scan over `app/` that flags a second implementation
 * (`tests/Feature/Compliance/EmailNormalizationGuardTest.php`).
 */
final class EmailNormalizer
{
    /**
     * Canonical form of a submitted e-mail: surrounding whitespace removed
     * and every character lower-cased with multibyte awareness.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
