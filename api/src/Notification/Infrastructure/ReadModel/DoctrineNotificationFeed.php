<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\ReadModel;

use App\Notification\Application\ReadModel\NotificationFeed;
use App\Notification\Application\ReadModel\NotificationView;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/** Lecture du centre de notifications (SQL direct, FAIL-CLOSED tenant — ADR-0013). */
final class DoctrineNotificationFeed implements NotificationFeed
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function latest(int $limit): array
    {
        $tenant = $this->tenantContext->require();

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, type, payload, read_at, occurred_on
             FROM notification
             WHERE tenant_id = :tenant
             ORDER BY occurred_on DESC
             LIMIT :limit',
            ['tenant' => $tenant->toString(), 'limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return array_map(static function (array $row): NotificationView {
            $decoded = json_decode(\is_string($row['payload']) ? $row['payload'] : '{}', true);
            /** @var array<string, mixed> $payload clés = noms de champs JSON (jsonb objet) */
            $payload = \is_array($decoded) ? $decoded : [];

            return new NotificationView(
                \is_string($row['id']) ? $row['id'] : '',
                \is_string($row['type']) ? $row['type'] : '',
                $payload,
                \is_string($row['read_at']) ? $row['read_at'] : null,
                \is_string($row['occurred_on']) ? $row['occurred_on'] : '',
            );
        }, $rows);
    }
}
