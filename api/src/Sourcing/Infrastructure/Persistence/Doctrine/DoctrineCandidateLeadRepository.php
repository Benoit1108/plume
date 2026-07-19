<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\CandidateLead\CandidateLead;
use App\Sourcing\Domain\CandidateLead\CandidateLeadId;
use App\Sourcing\Domain\CandidateLead\CandidateLeadRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCandidateLeadRepository implements CandidateLeadRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {
    }

    public function save(CandidateLead $candidate): void
    {
        $this->em->persist($candidate);
        $this->em->flush();
    }

    public function find(CandidateLeadId $id): ?CandidateLead
    {
        // Requête (et non find()) pour que le SQLFilter tenant s'applique — fail-closed.
        $candidate = $this->em->createQueryBuilder()
            ->select('c')
            ->from(CandidateLead::class, 'c')
            ->where('c.id = :id')
            ->setParameter('id', $id->toString())
            ->getQuery()
            ->getOneOrNullResult();

        return $candidate instanceof CandidateLead ? $candidate : null;
    }

    public function existsByDedupHash(TenantId $tenantId, string $dedupHash): bool
    {
        // Ingestion possiblement hors contexte requête (worker) : tenant EXPLICITE, fail-closed.
        $found = $this->connection->fetchOne(
            'SELECT 1 FROM candidate_lead WHERE tenant_id = :tenant AND dedup_hash = :hash LIMIT 1',
            ['tenant' => $tenantId->toString(), 'hash' => $dedupHash],
        );

        return false !== $found;
    }
}
