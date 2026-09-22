<?php

namespace App\Enums;

/**
 * Catalog of administrative audit slugs (RF-20). Exactly these eight; an
 * addition is only made when a real sensitive administrative operation
 * exists in the code.
 */
enum UserAdminAction: string
{
    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case RoleChanged = 'role_changed';
    case ObraAccessChanged = 'obra_access_changed';
    case UserActivated = 'user_activated';
    case UserDeactivated = 'user_deactivated';
    case AccessLinkSent = 'access_link_sent';
    case AccessLinkResent = 'access_link_resent';
}
