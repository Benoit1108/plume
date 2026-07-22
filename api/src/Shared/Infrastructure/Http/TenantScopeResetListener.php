<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En fin de requête HTTP (après réponse envoyée) : remet le tenant à zéro. Le tenant est posé en
 * session Postgres (`app.current_tenant`) et le filtre Doctrine reste activé sur l'EntityManager —
 * or FrankenPHP réutilise process ET connexion d'une requête à l'autre. Sans ce reset, l'état
 * fuirait vers la requête suivante (typiquement un /login non tenanté hériterait du tenant
 * précédent). Symétrique du `clear()` worker (middleware Messenger).
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final class TenantScopeResetListener
{
    public function __construct(private readonly TenantScope $tenantScope)
    {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $this->tenantScope->clear();
    }
}
