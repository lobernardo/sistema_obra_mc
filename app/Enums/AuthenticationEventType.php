<?php

namespace App\Enums;

/**
 * Catalog of authentication trail slugs (RF-26, CT-03). Exactly these six
 * (decisions D-04, D-08, D-09): there is no "changed" slug because no
 * authenticated change-password flow exists; `logout` is only the
 * explicit `POST /logout`, while every cut made by the system (deactivation,
 * credential change) is `session_revoked`.
 */
enum AuthenticationEventType: string
{
    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case PasswordReset = 'password_reset';
    case PasswordDefined = 'password_defined';
    case SessionRevoked = 'session_revoked';
}
