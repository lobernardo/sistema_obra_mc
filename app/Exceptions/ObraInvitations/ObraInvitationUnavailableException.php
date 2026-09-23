<?php

namespace App\Exceptions\ObraInvitations;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * The single outcome of every invalid convite (RF-28): expired, used,
 * revoked, malformed, unknown, bound to a Concluído obra, or lost to a
 * concurrent consumption (RF-32). The message is fixed and the exception
 * carries no context — no token, hash, convite id or obra — so the six
 * causes are indistinguishable and nothing secret can leak through it
 * (RF-38).
 *
 * The convite page catches it and redirects to the fixed token-free page;
 * `render()` is only a fallback that serves the same generic 404 view, and
 * `report()` marks it as handled so an invalid attempt never reaches the
 * log.
 */
class ObraInvitationUnavailableException extends RuntimeException
{
    public const MESSAGE = 'Este convite é inválido, expirou ou já foi utilizado. Peça um novo convite à Gestão ou a Suprimentos.';

    public static function make(): self
    {
        return new self(self::MESSAGE);
    }

    public function render(Request $request): Response
    {
        return response()->view('obra-invitations.unavailable', [], 404);
    }

    public function report(): bool
    {
        return true;
    }
}
