<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security;

use App\Account\Infrastructure\Persistence\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Le back-office manie une connexion PROPRIÉTAIRE (cross-tenant) : la 2FA y est OBLIGATOIRE (revue
 * globale sécu). Ce listener refuse toute route `/api/v1/admin/*` si l'admin authentifié n'a pas
 * activé sa 2FA (403 avec message stable `admin_2fa_required`). Pas de poule/œuf : l'enrôlement se
 * fait sur `/account/2fa`, hors du périmètre admin — un admin frais active sa 2FA puis accède au BO.
 */
final class AdminTwoFactorRequiredListener
{
    public function __construct(private readonly Security $security)
    {
    }

    #[AsEventListener(event: RequestEvent::class, priority: 4)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !str_starts_with($event->getRequest()->getPathInfo(), '/api/v1/admin')) {
            return;
        }

        // Seulement pour un ADMIN authentifié : un non-admin est de toute façon refusé par
        // l'access_control (ROLE_ADMIN) — on ne lui vole pas son 403.
        $user = $this->security->getUser();
        if ($user instanceof User && $this->security->isGranted('ROLE_ADMIN') && !$user->isTotpEnabled()) {
            throw new AccessDeniedHttpException('admin_2fa_required');
        }
    }
}
