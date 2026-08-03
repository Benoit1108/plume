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
 * d'action à effet EXTERNE ou coûteuse : connecter une vraie boîte OAuth et envoyer de vrais emails.
 * (La génération IA payante est neutralisée séparément par `AiGenerationPolicy` → repli canned.).
 *
 * Placé sur CONTROLLER (après l'auth). 403 `demo_restricted` sur la liste noire, sinon laisse passer.
 */
#[AsEventListener(event: ControllerEvent::class)]
final class DemoRestrictionListener
{
    /** Actions à effet externe interdites en démo (connexion/relève de boîte réelle). */
    private const array BLOCKED_EXACT = [
        '/api/v1/mailbox/oauth/start',
        '/api/v1/mailbox/connect',
        '/api/v1/mailbox/fetch-replies',
        '/api/v1/mailbox/fetch-alerts',
    ];

    /** Envoi réel d'un email : `/api/v1/drafts/{id}/send`. */
    private const string BLOCKED_SEND_PATTERN = '#^/api/v1/drafts/[^/]+/send$#';

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

        $path = $event->getRequest()->getPathInfo();
        $blocked = \in_array($path, self::BLOCKED_EXACT, true)
            || 1 === preg_match(self::BLOCKED_SEND_PATTERN, $path);

        if ($blocked) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'demo_restricted');
        }
    }
}
