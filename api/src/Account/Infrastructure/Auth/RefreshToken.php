<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Auth;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Entité concrète du refresh token (gesdinet 2.0 fournit une mapped-superclass).
 * Mappée en XML (config/doctrine/account/RefreshToken.orm.xml) pour hériter des
 * champs de la superclasse sans attributs sur des propriétés héritées.
 */
class RefreshToken extends BaseRefreshToken
{
}
