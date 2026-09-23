<?php

namespace App\Enums;

/**
 * Catalog of obra/convite audit slugs (CT-07 b, RF-02, RF-34). Exactly
 * these five.
 */
enum ObraAdminAction: string
{
    case ObraCreated = 'obra_created';
    case ObraUpdated = 'obra_updated';
    case InvitationCreated = 'invitation_created';
    case InvitationRevoked = 'invitation_revoked';
    case InvitationUsed = 'invitation_used';
}
