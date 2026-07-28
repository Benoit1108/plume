<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — comptes (GET /api/v1/admin/accounts?q=, ROLE_ADMIN). Liste les comptes des
 * traductrices (les administrateurs n'y figurent pas) avec des COMPTAGES par tenant — jamais de
 * contenu métier (minimisation). Recherche par email, bornée à 100 lignes.
 */
#[AsController]
final class AdminAccountsController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->admin->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    u.tenant_id,
                    u.email,
                    u.email_verified,
                    u.deletion_requested_at,
                    (SELECT COUNT(*) FROM organization o WHERE o.tenant_id = u.tenant_id) AS organizations,
                    (SELECT COUNT(*) FROM lead l WHERE l.tenant_id = u.tenant_id) AS leads,
                    (SELECT m.status FROM connected_mailbox m WHERE m.tenant_id = u.tenant_id LIMIT 1) AS mailbox_status
                FROM app_user u
                WHERE u.roles::text NOT LIKE '%ROLE_ADMIN%'
                  AND (:q = '' OR u.email ILIKE :like)
                ORDER BY u.email
                LIMIT 100
                SQL,
            ['q' => $q, 'like' => '%'.$q.'%'],
        );

        return new JsonResponse(['accounts' => array_map(static fn (array $row): array => [
            'tenantId' => \is_string($row['tenant_id']) ? $row['tenant_id'] : '',
            'email' => \is_string($row['email']) ? $row['email'] : '',
            'emailVerified' => (bool) $row['email_verified'],
            'deletionRequestedAt' => \is_string($row['deletion_requested_at']) ? $row['deletion_requested_at'] : null,
            'organizations' => is_numeric($row['organizations'] ?? null) ? (int) $row['organizations'] : 0,
            'leads' => is_numeric($row['leads'] ?? null) ? (int) $row['leads'] : 0,
            'mailboxStatus' => \is_string($row['mailbox_status']) ? $row['mailbox_status'] : 'NONE',
        ], $rows)]);
    }
}
