<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Auth;

use App\Shared\Application\Clock;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Marque chaque session (refresh token) au moment de sa création : appareil + horodatage. C'est le
 * seul point de passage commun au LOGIN et au RAFRAÎCHISSEMENT — sous rotation `single_use`, un
 * rafraîchissement supprime la ligne et en recrée une, donc la ligne courante porte toujours la
 * dernière activité de la session.
 */
#[AsEntityListener(event: Events::prePersist, entity: RefreshToken::class)]
final class StampRefreshTokenListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Clock $clock,
    ) {
    }

    public function prePersist(RefreshToken $token, PrePersistEventArgs $event): void
    {
        $token->setLastSeenAt($this->clock->now());

        $userAgent = $this->requestStack->getCurrentRequest()?->headers->get('User-Agent');
        $token->setUserAgent(null !== $userAgent && '' !== $userAgent ? mb_substr($userAgent, 0, 255) : null);
    }
}
