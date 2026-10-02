<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Recognizes a write that raced an obra deletion (RNF-01) and turns it into
 * the PT-BR 422 "A obra informada não foi encontrada." instead of a 500.
 *
 * A match is either an FK violation (SQLSTATE `23503`) whose driver message
 * names one of the given obra FK constraints — so an unrelated FK bug is
 * never masked as "obra não encontrada" — or a deadlock (SQLSTATE `40P01`)
 * between the deletion and the racing write.
 */
final class ObraGoneViolation
{
    public const MESSAGE = 'A obra informada não foi encontrada.';

    private const FOREIGN_KEY_VIOLATION = '23503';

    private const DEADLOCK_DETECTED = '40P01';

    /**
     * @param  list<string>  $constraintNames
     */
    public static function matches(QueryException $exception, array $constraintNames): bool
    {
        $sqlState = self::sqlState($exception);

        if ($sqlState === self::DEADLOCK_DETECTED) {
            return true;
        }

        if ($sqlState !== self::FOREIGN_KEY_VIOLATION) {
            return false;
        }

        $message = $exception->getMessage().' '.implode(' ', array_map('strval', $exception->errorInfo ?? []));

        foreach ($constraintNames as $constraintName) {
            if (preg_match('/\b'.preg_quote($constraintName, '/').'\b/', $message)) {
                return true;
            }
        }

        return false;
    }

    public static function exception(string $field): ValidationException
    {
        return ValidationException::withMessages([$field => self::MESSAGE]);
    }

    private static function sqlState(QueryException $exception): string
    {
        return (string) ($exception->errorInfo[0] ?? $exception->getCode());
    }
}
