<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\AlertFeed\AlertFeed;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\AlertFeed\AlertFeedRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAlertFeedRepository implements AlertFeedRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(AlertFeed $feed): void
    {
        $this->em->persist($feed);
        $this->em->flush();
    }

    public function find(AlertFeedId $id): ?AlertFeed
    {
        // Requête (et non find()) pour que le SQLFilter tenant s'applique — fail-closed.
        $feed = $this->em->createQueryBuilder()
            ->select('f')
            ->from(AlertFeed::class, 'f')
            ->where('f.id = :id')
            ->setParameter('id', $id->toString())
            ->getQuery()
            ->getOneOrNullResult();

        return $feed instanceof AlertFeed ? $feed : null;
    }

    public function remove(AlertFeed $feed): void
    {
        $this->em->remove($feed);
        $this->em->flush();
    }

    public function activeForTenant(TenantId $tenantId): array
    {
        // Relève possiblement hors requête (worker) : tenant EXPLICITE dans la clause, fail-closed.
        /** @var list<AlertFeed> $feeds */
        $feeds = $this->em->createQueryBuilder()
            ->select('f')
            ->from(AlertFeed::class, 'f')
            ->where('f.tenantId = :tenant')
            ->andWhere('f.active = true')
            ->setParameter('tenant', $tenantId->toString())
            ->getQuery()
            ->getResult();

        return $feeds;
    }
}
