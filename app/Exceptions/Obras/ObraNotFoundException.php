<?php

namespace App\Exceptions\Obras;

use App\Support\ObraGoneViolation;
use RuntimeException;

/**
 * The obra an administrative Action was called for no longer exists: its
 * locked re-read inside the transaction returned nothing, because a
 * concurrent request deleted it (RNF-01). Thrown by `SetObraActiveAction`
 * and `DeleteObraAction`; `Obras\Form` catches it and redirects to
 * `obras.index` with the same PT-BR message as a flash, never a 404 or 500.
 */
class ObraNotFoundException extends RuntimeException
{
    public const MESSAGE = ObraGoneViolation::MESSAGE;

    public static function make(): self
    {
        return new self(self::MESSAGE);
    }
}
