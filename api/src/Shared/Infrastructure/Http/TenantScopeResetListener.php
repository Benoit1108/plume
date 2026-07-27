<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Remet le tenant à zéro aux DEUX bornes de la requête HTTP. Le tenant est posé en session Postgres
 * (`app.current_tenant`) et le filtre Doctrine reste activé sur l'EntityManager — or FrankenPHP
 * réutilise process ET connexion d'une requête à l'autre. Sans reset, l'état fuirait vers la requête
 * suivante (typiquement un /login non tenanté hériterait du tenant précédent). Symétrique du
 * `clear()` worker (middleware Messenger).
 *
 * - TERMINATE (après réponse) : nettoyage nominal en fin de requête.
 * - REQUEST (avant le firewall, priorité haute → AVANT que l'auth ne pose le tenant, `isMainRequest`) :
 *   filet DÉFENSIF si un TERMINATE précédent n'a pas tourné (crash, mode worker FrankenPHP) — chaque
 *   requête DÉMARRE sur une ardoise propre (V2.0-c). La priorité 4096 garantit qu'on n'efface pas le
 *   tenant fraîchement posé par l'authentification (firewall en priorité 8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequest', priority: 4096)]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'onTerminate')]
final class TenantScopeResetListener
{
    public function __construct(private readonly TenantScope $tenantScope)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->tenantScope->clear();
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->tenantScope->clear();
    }
}
