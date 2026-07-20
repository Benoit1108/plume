<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\AlertFeed\AlertFeed;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\AlertFeed\AlertFeedRepository;

final class InMemoryAlertFeedRepository implements AlertFeedRepository
{
    /** @var array<string, AlertFeed> */
    private array $byId = [];

    public function save(AlertFeed $feed): void
    {
        $this->byId[$feed->id()->toString()] = $feed;
    }

    public function find(AlertFeedId $id): ?AlertFeed
    {
        return $this->byId[$id->toString()] ?? null;
    }

    public function remove(AlertFeed $feed): void
    {
        unset($this->byId[$feed->id()->toString()]);
    }

    public function activeForTenant(TenantId $tenantId): array
    {
        return array_values(array_filter(
            $this->byId,
            static fn (AlertFeed $f): bool => $f->isActive() && $f->tenantId()->equals($tenantId),
        ));
    }

    public function countForTenant(TenantId $tenantId): int
    {
        return \count(array_filter($this->byId, static fn (AlertFeed $f): bool => $f->tenantId()->equals($tenantId)));
    }

    public function count(): int
    {
        return \count($this->byId);
    }
}
