<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Auth\RefreshToken;
use App\Account\Infrastructure\Persistence\User;
use App\Shared\Application\Clock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * RGPD — suppression du compte courant (page Compte). Soft-delete : on marque
 * `deletion_requested_at`, ce qui désactive IMMÉDIATEMENT l'accès (UserChecker) et coupe la relève
 * de fond (scheduler) ; la purge PHYSIQUE du tenant intervient après le délai de grâce (tick, V2.0-a2).
 *
 * On exige le mot de passe courant (geste irréversible — défense contre une session détournée) et on
 * limite le débit PAR utilisateur. La réponse révoque toutes les sessions et efface les cookies d'auth.
 */
#[AsController]
final class DeleteAccountController
{
    public function __construct(
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly Clock $clock,
        private readonly RateLimiterFactory $accountPasswordLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('DeleteAccountController behind the firewall: a user is always present.');
        }

        $limit = $this->accountPasswordLimiter->create($user->getUserIdentifier())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $payload = json_decode($request->getContent(), true);
        $current = \is_array($payload) && \is_string($payload['currentPassword'] ?? null) ? $payload['currentPassword'] : '';
        if (!$this->hasher->isPasswordValid($user, $current)) {
            return new JsonResponse(['detail' => 'invalid_current_password'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Soft-delete : marque la demande (idempotent). Le compte est dès lors inaccessible.
        $user->requestDeletion($this->clock->now());

        // Révoque toutes les sessions : plus aucun refresh ne vaut (l'accès est coupé net).
        foreach ($this->em->getRepository(RefreshToken::class)->findBy(['username' => $user->getUserIdentifier()]) as $token) {
            $this->em->remove($token);
        }
        $this->em->flush();

        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie('plume_jwt', '/', null, true, true, Cookie::SAMESITE_LAX);
        $response->headers->clearCookie('refresh_token', '/api/v1/token', null, true, true, Cookie::SAMESITE_LAX);

        return $response;
    }
}
