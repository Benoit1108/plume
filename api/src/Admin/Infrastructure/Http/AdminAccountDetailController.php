<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Back-office — FICHE COMPTE (GET /api/v1/admin/accounts/{tenantId}, ROLE_ADMIN). Vue détaillée d'un
 * compte pour le support : identité/état, dernière connexion & activité, 2FA, digest, boîte email,
 * volumétrie. Comptages seulement, jamais de contenu métier (minimisation, ADR-0026) ; connexion
 * `admin` (rôle propriétaire).
 */
#[AsController]
final class AdminAccountDetailController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
    ) {
    }

    public function __invoke(string $tenantId): Response
    {
        $user = $this->admin->fetchAssociative(
            <<<'SQL'
                SELECT email, email_verified, deletion_requested_at, created_at, last_login_at,
                       (totp_secret IS NOT NULL) AS two_factor_enabled
                FROM app_user
                WHERE tenant_id = :tenant AND roles::text NOT LIKE '%ROLE_ADMIN%'
                SQL,
            ['tenant' => $tenantId],
        );
        if (false === $user) {
            throw new NotFoundHttpException('Account not found.');
        }

        $mailbox = $this->admin->fetchAssociative(
            'SELECT provider, status FROM connected_mailbox WHERE tenant_id = :tenant',
            ['tenant' => $tenantId],
        );

        $digest = $this->admin->fetchOne('SELECT digest_frequency FROM profile WHERE tenant_id = :tenant', ['tenant' => $tenantId]);
        $subStatus = $this->admin->fetchOne('SELECT status FROM subscription WHERE tenant_id = :tenant', ['tenant' => $tenantId]);
        $lastActivity = $this->admin->fetchOne('SELECT MAX(occurred_on) FROM interaction WHERE tenant_id = :tenant', ['tenant' => $tenantId]);

        /** @var array<string, mixed> $counts */
        $counts = (array) $this->admin->fetchAssociative(
            <<<'SQL'
                SELECT
                    (SELECT COUNT(*) FROM organization WHERE tenant_id = :tenant) AS organizations,
                    (SELECT COUNT(*) FROM lead WHERE tenant_id = :tenant) AS leads,
                    (SELECT COUNT(*) FROM outbound_message WHERE tenant_id = :tenant AND status = 'SENT') AS messages_sent
                SQL,
            ['tenant' => $tenantId],
        );

        return new JsonResponse([
            'tenantId' => $tenantId,
            'email' => \is_string($user['email']) ? $user['email'] : '',
            'emailVerified' => (bool) $user['email_verified'],
            'deletionRequestedAt' => \is_string($user['deletion_requested_at'] ?? null) ? $user['deletion_requested_at'] : null,
            'createdAt' => \is_string($user['created_at'] ?? null) ? $user['created_at'] : null,
            'lastLoginAt' => \is_string($user['last_login_at'] ?? null) ? $user['last_login_at'] : null,
            'twoFactorEnabled' => (bool) $user['two_factor_enabled'],
            'digestFrequency' => \is_string($digest) ? $digest : 'DAILY',
            'subscriptionStatus' => \is_string($subStatus) ? $subStatus : 'none',
            'lastActivityAt' => \is_string($lastActivity) ? $lastActivity : null,
            'mailbox' => \is_array($mailbox) ? [
                'provider' => \is_string($mailbox['provider'] ?? null) ? $mailbox['provider'] : '',
                'status' => \is_string($mailbox['status'] ?? null) ? $mailbox['status'] : '',
            ] : null,
            'organizations' => is_numeric($counts['organizations'] ?? null) ? (int) $counts['organizations'] : 0,
            'leads' => is_numeric($counts['leads'] ?? null) ? (int) $counts['leads'] : 0,
            'messagesSent' => is_numeric($counts['messages_sent'] ?? null) ? (int) $counts['messages_sent'] : 0,
        ]);
    }
}
