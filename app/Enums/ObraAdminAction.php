<?php

namespace App\Enums;

/**
 * Catalog of obra/convite audit slugs (CT-07 b, RF-02, RF-34 and, from
 * `obras-ativacao-exclusao`, RF-23 / CT-02). Exactly these nine.
 */
enum ObraAdminAction: string
{
    case ObraCreated = 'obra_created';
    case ObraUpdated = 'obra_updated';
    case InvitationCreated = 'invitation_created';
    case InvitationRevoked = 'invitation_revoked';
    case InvitationUsed = 'invitation_used';
    case ObraDeactivated = 'obra_deactivated';
    case ObraReactivated = 'obra_reactivated';
    case ObraDeleted = 'obra_deleted';
    case ObraDeleteBlocked = 'obra_delete_blocked';
}
