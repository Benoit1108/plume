<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Auth\RefreshToken;
use App\Account\Infrastructure\Persistence\PasswordResetToken;
use App\Account\Infrastructure\Persistence\User;
use App\Shared\Application\Clock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Mot de passe oublié — réinitialisation via jeton (endpoint PUBLIC, V2.1a). Valide le jeton (hash,
 * non expiré, à usage unique), fixe le nouveau mot de passe, **révoque toutes les sessions** (refresh
 * tokens) et **consomme** le(s) jeton(s) de l'email. Messages d'erreur stables (`invalid_token` /
 * `invalid_new_password`), jamais de fuite d'information.
 */
#[AsController]
final class ResetPasswordController
{
    private const int MIN_LENGTH = 8;
    private const int MAX_LENGTH = 4096;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $token = \is_array($payload) && \is_string($payload['token'] ?? null) ? $payload['token'] : '';
        $new = \is_array($payload) && \is_string($payload['newPassword'] ?? null) ? $payload['newPassword'] : '';

        $length = mb_strlen($new);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return new JsonResponse(['detail' => 'invalid_new_password'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resetToken = '' === $token
            ? null
            : $this->em->getRepository(PasswordResetToken::class)->findOneBy(['tokenHash' => hash('sha256', $token)]);

        if (!$resetToken instanceof PasswordResetToken || $resetToken->isExpired($this->clock->now())) {
            if ($resetToken instanceof PasswordResetToken) {
                $this->em->remove($resetToken); // jeton expiré : on le purge
                $this->em->flush();
            }

            return new JsonResponse(['detail' => 'invalid_token'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email = $resetToken->email();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $this->em->remove($resetToken);
            $this->em->flush();

            return new JsonResponse(['detail' => 'invalid_token'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setPassword($this->hasher->hashPassword($user, $new));

        // Révoque toutes les sessions (comme le changement de mot de passe connecté).
        foreach ($this->em->getRepository(RefreshToken::class)->findBy(['username' => $email]) as $refresh) {
            $this->em->remove($refresh);
        }
        // Consomme le(s) jeton(s) de réinitialisation de cet email (usage unique).
        foreach ($this->em->getRepository(PasswordResetToken::class)->findBy(['email' => $email]) as $used) {
            $this->em->remove($used);
        }
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
