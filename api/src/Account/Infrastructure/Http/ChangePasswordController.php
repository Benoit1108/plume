<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Changement du mot de passe de l'utilisateur courant (page Compte). Derrière le
 * firewall (ROLE_USER via access_control ^/api/v1). On exige l'ancien mot de passe
 * (défense contre une session détournée) et on limite le débit PAR utilisateur
 * (anti force brute sur l'ancien mot de passe).
 */
#[AsController]
final class ChangePasswordController
{
    private const int MIN_LENGTH = 8;
    private const int MAX_LENGTH = 4096;

    public function __construct(
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly RateLimiterFactory $accountPasswordLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('ChangePasswordController behind the firewall: a user is always present.');
        }

        $limit = $this->accountPasswordLimiter->create($user->getUserIdentifier())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $payload = json_decode($request->getContent(), true);
        $current = \is_array($payload) && \is_string($payload['currentPassword'] ?? null) ? $payload['currentPassword'] : '';
        $new = \is_array($payload) && \is_string($payload['newPassword'] ?? null) ? $payload['newPassword'] : '';

        $length = mb_strlen($new);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return new JsonResponse(['detail' => 'invalid_new_password'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$this->hasher->isPasswordValid($user, $current)) {
            return new JsonResponse(['detail' => 'invalid_current_password'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($this->hasher->hashPassword($user, $new));
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
