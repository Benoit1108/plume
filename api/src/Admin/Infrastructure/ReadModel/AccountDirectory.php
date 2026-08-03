<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\ReadModel;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lecture des comptes des traductrices pour le back-office (hors administrateurs), avec recherche,
 * filtre de statut et tri. Source unique partagée par la liste JSON et l'export CSV — jamais de
 * contenu métier (comptages seulement, minimisation). Connexion `admin` (rôle propriétaire, ADR-0026).
 */
final class AccountDirectory
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
    ) {
    }

    /**
     * @return list<array{tenantId: string, email: string, emailVerified: bool, deletionRequestedAt: ?string, createdAt: ?string, organizations: int, leads: int, mailboxStatus: string}>
     */
    public function list(string $q, string $status, string $sort, int $limit): array
    {
        // Fragments en LISTE BLANCHE (ORDER BY / prédicats ne se bindent pas) : jamais d'entrée brute.
        $statusPredicate = match ($status) {
            'verified' => 'AND u.email_verified = true AND u.deletion_requested_at IS NULL',
            'unverified' => 'AND u.email_verified = false',
            'deleting' => 'AND u.deletion_requested_at IS NOT NULL',
            default => '',
        };
        $orderBy = match ($sort) {
            'leads' => 'leads DESC, u.email',
            'created' => 'u.created_at DESC NULLS LAST, u.email',
            default => 'u.email',
        };

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->admin->fetchAllAssociative(
            sprintf(
                <<<'SQL'
                    SELECT
                        u.tenant_id, u.email, u.email_verified, u.deletion_requested_at, u.created_at,
                        (SELECT COUNT(*) FROM organization o WHERE o.tenant_id = u.tenant_id) AS organizations,
                        (SELECT COUNT(*) FROM lead l WHERE l.tenant_id = u.tenant_id) AS leads,
                        (SELECT m.status FROM connected_mailbox m WHERE m.tenant_id = u.tenant_id LIMIT 1) AS mailbox_status
                    FROM app_user u
                    WHERE u.roles::text NOT LIKE '%%ROLE_ADMIN%%'
                      AND (:q = '' OR u.email ILIKE :like)
                      %s
                    ORDER BY %s
                    LIMIT :limit
                    SQL,
                $statusPredicate,
                $orderBy,
            ),
            ['q' => $q, 'like' => '%'.$q.'%', 'limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return array_map(static fn (array $row): array => [
            'tenantId' => \is_string($row['tenant_id']) ? $row['tenant_id'] : '',
            'email' => \is_string($row['email']) ? $row['email'] : '',
            'emailVerified' => (bool) $row['email_verified'],
            'deletionRequestedAt' => \is_string($row['deletion_requested_at']) ? $row['deletion_requested_at'] : null,
            'createdAt' => \is_string($row['created_at']) ? $row['created_at'] : null,
            'organizations' => is_numeric($row['organizations'] ?? null) ? (int) $row['organizations'] : 0,
            'leads' => is_numeric($row['leads'] ?? null) ? (int) $row['leads'] : 0,
            'mailboxStatus' => \is_string($row['mailbox_status']) ? $row['mailbox_status'] : 'NONE',
        ], $rows);
    }
}
