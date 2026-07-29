<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Mail\AccountMailer;
use App\Account\Infrastructure\Persistence\PasswordResetToken;
use App\Account\Infrastructure\Persistence\User;
use App\Shared\Application\Clock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Mot de passe oublié — demande de réinitialisation (endpoint PUBLIC, V2.1a). Émet un jeton à usage
 * unique (hash stocké, clair envoyé par email, valable 1 h). **Anti-énumération** : réponse TOUJOURS
 * 204, qu'un compte existe ou non. Débit limité PAR IP (anti-spam d'emails). Un seul jeton actif par
 * email (les précédents sont purgés).
 */
#[AsController]
final class ForgotPasswordController
{
    private const string TOKEN_TTL = '+1 hour';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountMailer $mailer,
        private readonly Clock $clock,
        private readonly RateLimiterFactory $passwordResetLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $limit = $this->passwordResetLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $payload = json_decode($request->getContent(), true);
        // Normalisée en minuscules comme à l'inscription (revue globale P0-2) : sinon un email saisi
        // avec une autre casse ne matcherait pas et le 204 anti-énumération masquerait le blocage.
        $email = \is_array($payload) && \is_string($payload['email'] ?? null) ? mb_strtolower(trim($payload['email'])) : '';

        if ('' !== $email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                foreach ($this->em->getRepository(PasswordResetToken::class)->findBy(['email' => $email]) as $old) {
                    $this->em->remove($old);
                }

                $now = $this->clock->now();
                $token = bin2hex(random_bytes(32));
                $this->em->persist(new PasswordResetToken(
                    Uuid::v7(),
                    $email,
                    hash('sha256', $token),
                    $now->modify(self::TOKEN_TTL),
                    $now,
                ));
                $this->em->flush();

                $this->mailer->sendPasswordReset($email, $token);
            }
        }

        // Toujours 204 : ne jamais révéler l'existence (ou non) d'un compte.
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
