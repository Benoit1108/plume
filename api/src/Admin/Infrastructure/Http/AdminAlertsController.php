<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — SANTÉ & ALERTES (GET /api/v1/admin/alerts, ROLE_ADMIN). La liste « à regarder » :
 * comptes actifs devenus INACTIFS (>30 j sans acte), boîtes email EN ERREUR (reconnexion à
 * demander), inscriptions restées NON VÉRIFIÉES (>7 j, potentiellement bloquées). Comptages/emails
 * seulement (minimisation) ; connexion `admin` (rôle propriétaire, cross-tenant — ADR-0026).
 */
#[AsController]
final class AdminAlertsController
{
    private const int INACTIVE_DAYS = 30;
    private const int STUCK_VERIFICATION_DAYS = 7;
    private const int LIMIT = 50;

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
    ) {
    }

    public function __invoke(): Response
    {
        return new JsonResponse([
            'inactiveAccounts' => $this->inactiveAccounts(),
            'mailboxesInError' => $this->mailboxesInError(),
            'stuckVerification' => $this->stuckVerification(),
        ]);
    }

    /** @return list<array{email: string, lastActivityAt: ?string}> */
    private function inactiveAccounts(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->admin->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                    SELECT u.email, la.last_activity
                    FROM app_user u
                    LEFT JOIN (SELECT tenant_id, MAX(occurred_on) AS last_activity FROM interaction GROUP BY tenant_id) la
                           ON la.tenant_id = u.tenant_id
                    WHERE u.roles::text NOT LIKE '%%ROLE_ADMIN%%'
                      AND u.email_verified = true
                      AND u.deletion_requested_at IS NULL
                      AND (la.last_activity IS NULL OR la.last_activity < NOW() - INTERVAL '%d days')
                    ORDER BY la.last_activity ASC NULLS FIRST
                    LIMIT %d
                    SQL,
                self::INACTIVE_DAYS,
                self::LIMIT,
            ),
        );

        return array_map(static fn (array $row): array => [
            'email' => \is_string($row['email']) ? $row['email'] : '',
            'lastActivityAt' => \is_string($row['last_activity'] ?? null) ? $row['last_activity'] : null,
        ], $rows);
    }

    /** @return list<array{email: string}> */
    private function mailboxesInError(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->admin->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                    SELECT u.email
                    FROM connected_mailbox m
                    JOIN app_user u ON u.tenant_id = m.tenant_id
                    WHERE m.status = 'ERROR'
                    ORDER BY u.email
                    LIMIT %d
                    SQL,
                self::LIMIT,
            ),
        );

        return array_map(static fn (array $row): array => ['email' => \is_string($row['email']) ? $row['email'] : ''], $rows);
    }

    /** @return list<array{email: string, createdAt: ?string}> */
    private function stuckVerification(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->admin->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                    SELECT u.email, u.created_at
                    FROM app_user u
                    WHERE u.roles::text NOT LIKE '%%ROLE_ADMIN%%'
                      AND u.email_verified = false
                      AND u.deletion_requested_at IS NULL
                      AND u.created_at < NOW() - INTERVAL '%d days'
                    ORDER BY u.created_at ASC
                    LIMIT %d
                    SQL,
                self::STUCK_VERIFICATION_DAYS,
                self::LIMIT,
            ),
        );

        return array_map(static fn (array $row): array => [
            'email' => \is_string($row['email']) ? $row['email'] : '',
            'createdAt' => \is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
        ], $rows);
    }
}
