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
 * Back-office — JOURNAL D'AUDIT (GET /api/v1/admin/audit, ROLE_ADMIN). Rend consultable la table
 * `audit_log` (hors tenant, ADR-0025/0026) : connexions admin, suppressions demandées, purges,
 * resets 2FA. Lecture seule, bornée, filtrable par action ; connexion `admin` (rôle propriétaire).
 */
#[AsController]
final class AdminAuditController
{
    private const int MAX_LIMIT = 200;

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $action = trim((string) $request->query->get('action', ''));
        $limit = max(1, min(self::MAX_LIMIT, $request->query->getInt('limit', 100)));

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->admin->fetchAllAssociative(
            <<<'SQL'
                SELECT id, actor, action, target, details, occurred_at
                FROM audit_log
                WHERE (:action = '' OR action = :action)
                ORDER BY occurred_at DESC
                LIMIT :limit
                SQL,
            ['action' => $action, 'limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return new JsonResponse(['entries' => array_map(static function (array $row): array {
            $details = \is_string($row['details'] ?? null) ? json_decode($row['details'], true) : null;

            return [
                'id' => \is_string($row['id']) ? $row['id'] : '',
                'actor' => \is_string($row['actor']) ? $row['actor'] : '',
                'action' => \is_string($row['action']) ? $row['action'] : '',
                'target' => \is_string($row['target']) ? $row['target'] : '',
                'details' => \is_array($details) ? $details : [],
                'occurredAt' => \is_string($row['occurred_at']) ? $row['occurred_at'] : '',
            ];
        }, $rows)]);
    }
}
