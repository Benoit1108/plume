<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Mail\AccountMailer;
use App\Account\Infrastructure\Persistence\User;
use App\Account\Infrastructure\Security\EmailVerificationSigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Renvoi de l'email de vérification (endpoint PUBLIC, revue globale P0-1) : un compte dont l'email
 * n'a pas été confirmé (email perdu / spam / lien expiré 24 h) serait sinon mort-né. Réponse
 * TOUJOURS 204 (anti-énumération), débit limité PAR IP, envoi seulement si le compte existe ET
 * n'est PAS déjà vérifié (rien à renvoyer sinon).
 */
#[AsController]
final class ResendVerificationController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountMailer $mailer,
        private readonly EmailVerificationSigner $signer,
        private readonly RateLimiterFactory $registrationLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $limit = $this->registrationLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $payload = json_decode($request->getContent(), true);
        $email = \is_array($payload) && \is_string($payload['email'] ?? null) ? mb_strtolower(trim($payload['email'])) : '';

        if ('' !== $email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user instanceof User && !$user->isEmailVerified()) {
                $this->mailer->sendEmailVerification($email, $this->signer->sign($email));
            }
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
