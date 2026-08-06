<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Auth;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * Entité concrète du refresh token (gesdinet 2.0 fournit une mapped-superclass).
 * Mappée en XML (config/doctrine/account/RefreshToken.orm.xml) pour hériter des
 * champs de la superclasse sans attributs sur des propriétés héritées.
 *
 * Deux colonnes ajoutées (lot « densité ») pour rendre les sessions IDENTIFIABLES sur la page
 * Compte : l'appareil (User-Agent brut, résumé à l'affichage par {@see DeviceLabel}) et la dernière
 * activité. Sous rotation `single_use`, chaque rafraîchissement recrée la ligne : la date de
 * création EST donc la dernière activité de la session (posée par {@see StampRefreshTokenListener}).
 */
class RefreshToken extends BaseRefreshToken
{
    private ?string $userAgent = null;

    private ?\DateTimeImmutable $lastSeenAt = null;

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): void
    {
        $this->lastSeenAt = $lastSeenAt;
    }
}
