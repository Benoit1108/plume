<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Persistence\Doctrine;

use App\Sourcing\Domain\RawAlert\RawAlert;
use App\Sourcing\Domain\RawAlert\RawAlertRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * Table support `raw_alert` (non mappée ORM, patron de la projection `interaction`) :
 * INSERT direct en DBAL avec tenant EXPLICITE (fail-closed, utilisable hors requête).
 */
final class DoctrineRawAlertRepository implements RawAlertRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(RawAlert $rawAlert): void
    {
        $this->connection->insert('raw_alert', [
            'id' => $rawAlert->id()->toString(),
            'tenant_id' => $rawAlert->tenantId()->toString(),
            'source' => $rawAlert->source()->value,
            'payload' => $rawAlert->payload(),
            'fetched_at' => $rawAlert->fetchedAt(),
        ], [
            'fetched_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }
}
