<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use App\Account\Infrastructure\Security\EmailVerificationSigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Vérification d'email (V2.1b, PUBLIC) : valide le jeton signé (HMAC + expiration) et marque le
 * compte comme vérifié — dès lors l'authentification est autorisée. Idempotent (re-vérifier est un
 * no-op). Jeton invalide/expiré → 422 `invalid_token`.
 */
#[AsController]
final class VerifyEmailController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailVerificationSigner $signer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $token = \is_array($payload) && \is_string($payload['token'] ?? null) ? $payload['token'] : '';

        $email = $this->signer->verify($token);
        if (null === $email) {
            return new JsonResponse(['detail' => 'invalid_token'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            $user->verifyEmail();
            $this->em->flush();
        }

        // 204 même si le compte n'existe plus : le jeton était valide, rien à divulguer.
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
