<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Bride les capacités d'un compte de DÉMONSTRATION public (`ROLE_DEMO`, vitrine V2). L'endpoint
 * `/api/v1/demo` étant ouvert à un visiteur anonyme, sa session ne doit pas pouvoir déclencher
 * d'action à effet EXTERNE ou coûteuse : connecter une vraie boîte OAuth, envoyer de vrais emails,
 * ni faire émettre au serveur des requêtes SORTANTES vers une URL choisie par le visiteur.
 * (La génération IA payante est neutralisée séparément par `AiGenerationPolicy` → repli canned.).
 *
 * Placé sur CONTROLLER (après l'auth). 403 `demo_restricted` sur la liste noire, sinon laisse passer.
 */
#[AsEventListener(event: ControllerEvent::class)]
final class DemoRestrictionListener
{
    /**
     * Actions à effet externe interdites en démo.
     *
     * Les SOURCES en font partie (revue SEC-P2b) : `POST /sources` enregistre une URL arbitraire et
     * `/sources/poll` la fait relever PAR LE SERVEUR. Les IP privées sont déjà refusées
     * (`NoPrivateNetworkHttpClient`), mais laisser un visiteur anonyme déclencher des requêtes
     * sortantes attribuables à Plume — vers l'hôte de son choix — n'a aucune contrepartie : le tenant
     * de démo est pré-rempli d'annonces factices, il n'a pas besoin de flux réel.
     */
    private const array BLOCKED_EXACT = [
        '/api/v1/mailbox/oauth/start',
        '/api/v1/mailbox/connect',
        '/api/v1/mailbox/fetch-replies',
        '/api/v1/mailbox/fetch-alerts',
        '/api/v1/sources/poll',
    ];

    /** Envoi réel d'un email : `/api/v1/drafts/{id}/send`. */
    private const string BLOCKED_SEND_PATTERN = '#^/api/v1/drafts/[^/]+/send$#';

    /**
     * Sources : seules les ÉCRITURES sont bloquées (ajout, activation, retrait). La lecture reste
     * ouverte — l'écran Réglages « Sources » doit rester visitable en démo, c'est ce qu'on montre.
     */
    private const string SOURCES_PREFIX = '/api/v1/sources';
    private const array WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token || !\in_array('ROLE_DEMO', $token->getRoleNames(), true)) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $blocked = \in_array($path, self::BLOCKED_EXACT, true)
            || 1 === preg_match(self::BLOCKED_SEND_PATTERN, $path)
            || (str_starts_with($path, self::SOURCES_PREFIX) && \in_array($request->getMethod(), self::WRITE_METHODS, true));

        if ($blocked) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'demo_restricted');
        }
    }
}
