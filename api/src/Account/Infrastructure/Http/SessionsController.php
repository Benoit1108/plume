<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Auth\DeviceLabel;
use App\Account\Infrastructure\Auth\RefreshToken;
use App\Account\Infrastructure\Persistence\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Sessions actives (V2, slice sécurité) : les refresh tokens SONT les sessions. La session
 * COURANTE est identifiée par le cookie httpOnly `refresh_token` (jamais renvoyé au front — on ne
 * renvoie que l'id de ligne, l'appareil et les dates). Révocation unitaire ou « toutes sauf la
 * courante », strictement bornées à l'utilisateur connecté.
 *
 * Lot « densité » : chaque session porte son appareil (navigateur/plateforme) et sa dernière
 * activité, sans quoi les lignes sont interchangeables et impossibles à reconnaître. Le volume,
 * lui, est tenu par {@see \App\Account\Infrastructure\Auth\PruneSessionsListener}.
 */
#[AsController]
final class SessionsController
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function list(Request $request): Response
    {
        $user = $this->currentUser();
        $currentToken = (string) $request->cookies->get('refresh_token', '');

        $sessions = [];
        foreach ($this->tokensOf($user) as $token) {
            // Navigateur/plateforme plutôt que le User-Agent brut : reconnaissable sans être un
            // empreintage. `null` quand l'agent est absent/inconnu — le front traduit le repli.
            $device = DeviceLabel::fromUserAgent($token->getUserAgent());

            $sessions[] = [
                'id' => $token->getId(),
                'browser' => $device->browser,
                'platform' => $device->platform,
                'lastSeenAt' => $token->getLastSeenAt()?->format(\DATE_ATOM),
                'expiresAt' => $token->getValid()?->format(\DATE_ATOM),
                'current' => '' !== $currentToken && $token->getRefreshToken() === $currentToken,
            ];
        }

        return new JsonResponse(['sessions' => $sessions]);
    }

    public function revoke(Request $request, int $id): Response
    {
        $user = $this->currentUser();
        foreach ($this->tokensOf($user) as $token) {
            if ($token->getId() === $id) { // borné aux tokens de CE compte
                $this->em->remove($token);
                $this->em->flush();
                break;
            }
        }

        // 204 même si inconnu/étranger : rien à divulguer.
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function revokeOthers(Request $request): Response
    {
        $user = $this->currentUser();
        $currentToken = (string) $request->cookies->get('refresh_token', '');

        foreach ($this->tokensOf($user) as $token) {
            if ('' === $currentToken || $token->getRefreshToken() !== $currentToken) {
                $this->em->remove($token);
            }
        }
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @return list<RefreshToken> — les plus récentes d'abord (l'id est séquentiel). */
    private function tokensOf(User $user): array
    {
        /** @var list<RefreshToken> $tokens */
        $tokens = $this->em->getRepository(RefreshToken::class)
            ->findBy(['username' => $user->getUserIdentifier()], ['id' => 'DESC']);

        return $tokens;
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('SessionsController behind the firewall: a user is always present.');
        }

        return $user;
    }
}
