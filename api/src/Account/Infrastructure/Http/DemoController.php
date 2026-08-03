<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Demo\DemoSeeder;
use App\Account\Infrastructure\Persistence\User;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Vitrine — « Essayer la démo » (POST /api/v1/demo, PUBLIC). Crée un tenant ÉPHÉMÈRE isolé + un
 * compte démo (rôle ROLE_DEMO, expirant), le pré-remplit de données fictives, et connecte
 * directement la visiteuse (cookies JWT via le success handler lexik). Le tenant est purgé après
 * `demo_expires_at` par un tick (réutilise la purge RGPD). Débit limité PAR IP (anti-abus).
 *
 * Sûr par construction : aucune boîte connectée (envois factices), aucune clé IA (canned gratuit),
 * tenant isolé (RLS). Aucun email réel n'est envoyé.
 */
#[AsController]
final class DemoController
{
    private const string DEMO_TTL = '+2 hours';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly Connection $connection,
        private readonly TenantScope $tenantScope,
        private readonly DemoSeeder $seeder,
        private readonly AuthenticationSuccessHandler $successHandler,
        private readonly RateLimiterFactory $demoLimiter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $limit = $this->demoLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $tenantId = Uuid::v7();
        $email = 'demo-'.bin2hex(random_bytes(6)).'@demo.plume.local';
        $user = new User(Uuid::v7(), $tenantId, $email);
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(16)))); // jamais utilisé
        $user->setRoles(['ROLE_DEMO']);
        $this->em->persist($user);
        $this->em->flush();

        // Expiration (colonne hors ORM) : marque le compte comme démo → purgé par le tick.
        $this->connection->executeStatement(
            'UPDATE app_user SET demo_expires_at = :expires WHERE tenant_id = :tenant',
            ['expires' => (new \DateTimeImmutable(self::DEMO_TTL))->format('Y-m-d H:i:s'), 'tenant' => $tenantId->toRfc4122()],
        );

        // Pré-remplissage sous le tenant démo (scope actif → RLS satisfaite ; nettoyé en fin de requête).
        $this->tenantScope->activate(TenantId::fromString($tenantId->toRfc4122()));
        $this->seeder->seed($tenantId->toRfc4122());

        // Connexion directe : le success handler pose les cookies JWT (+ refresh) selon la config lexik.
        return $this->successHandler->handleAuthenticationSuccess($user);
    }
}
