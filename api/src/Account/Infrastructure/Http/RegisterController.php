<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Mail\AccountMailer;
use App\Account\Infrastructure\Persistence\User;
use App\Account\Infrastructure\Security\EmailVerificationSigner;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Inscription publique en libre-service (V2.1b). Crée un tenant + un compte NON vérifié (l'auth est
 * refusée tant que l'email n'est pas confirmé) et envoie l'email de vérification. Débit limité PAR IP.
 * Le profil métier est créé paresseusement au premier accès (comportement existant).
 *
 * Choix produit : email déjà pris ⇒ 409 explicite (UX claire ; l'énumération est déjà possible via
 * login/mot-de-passe-oublié). L'acceptation des CGU est requise.
 */
#[AsController]
final class RegisterController
{
    private const int MIN_PASSWORD = 8;
    private const int MAX_PASSWORD = 4096;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
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
        $password = \is_array($payload) && \is_string($payload['password'] ?? null) ? $payload['password'] : '';
        $acceptTerms = \is_array($payload) && true === ($payload['acceptTerms'] ?? null);

        if ('' === $email || false === filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            return new JsonResponse(['detail' => 'invalid_email'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $length = mb_strlen($password);
        if ($length < self::MIN_PASSWORD || $length > self::MAX_PASSWORD) {
            return new JsonResponse(['detail' => 'invalid_password'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$acceptTerms) {
            return new JsonResponse(['detail' => 'terms_not_accepted'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            return new JsonResponse(['detail' => 'email_taken'], Response::HTTP_CONFLICT);
        }

        $user = new User(Uuid::v7(), Uuid::v7(), $email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->requireEmailVerification();
        $this->em->persist($user);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Course entre le findOneBy et le flush (deux inscriptions simultanées) → 409, pas 500.
            return new JsonResponse(['detail' => 'email_taken'], Response::HTTP_CONFLICT);
        }

        $this->mailer->sendEmailVerification($email, $this->signer->sign($email));

        return new JsonResponse(null, Response::HTTP_CREATED);
    }
}
