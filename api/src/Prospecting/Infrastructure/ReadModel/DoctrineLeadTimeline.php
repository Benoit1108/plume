<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ReadModel;

use App\Prospecting\Application\ReadModel\InteractionView;
use App\Prospecting\Application\ReadModel\LeadTimeline;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/** Journal d'interactions en SQL direct — scoping tenant FAIL-CLOSED. */
final class DoctrineLeadTimeline implements LeadTimeline
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function forLead(string $leadId): array
    {
        $tenant = $this->tenantContext->require();

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, type, payload, occurred_on FROM interaction
             WHERE tenant_id = :tenant AND lead_id = :lead
             ORDER BY occurred_on DESC, id DESC',
            ['tenant' => $tenant->toString(), 'lead' => $leadId],
        );

        return array_map(static function (array $row): InteractionView {
            $decoded = \is_string($row['payload'] ?? null) ? json_decode($row['payload'], true) : [];
            /** @var array<string, mixed> $payload */
            $payload = \is_array($decoded) ? $decoded : [];

            return new InteractionView(
                \is_string($row['id'] ?? null) ? $row['id'] : '',
                \is_string($row['type'] ?? null) ? $row['type'] : '',
                $payload,
                new \DateTimeImmutable(\is_string($row['occurred_on'] ?? null) ? $row['occurred_on'] : 'now'),
            );
        }, $rows);
    }
}
