<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\Doctrine;

use App\Prospecting\Domain\Lead\Exception\LeadNotFound;
use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
use App\Prospecting\Domain\Lead\PipelineStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineLeadRepository implements LeadRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Lead $lead): void
    {
        $this->em->persist($lead);
        $this->em->flush();
    }

    public function get(LeadId $id): Lead
    {
        // Requête (et non find()) pour que le SQLFilter tenant s'applique EN HTTP.
        // ⚠️ Hors HTTP (worker), le filtre est INACTIF : les commandes appelées en
        // asynchrone (RecordReply/RecordFollowUp/ContactLead) portent un tenantId
        // explicite vérifié par leur handler (fail-closed). Cf. revue fin M2.
        $lead = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Lead::class, 'l')
            ->where('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lead instanceof Lead) {
            throw LeadNotFound::withId($id);
        }

        return $lead;
    }

    public function hasActiveForOrganization(string $organizationId): bool
    {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(Lead::class, 'l')
            ->where('l.organizationId = :organizationId')
            ->andWhere('l.status NOT IN (:terminal)')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('terminal', [PipelineStatus::WON->value, PipelineStatus::LOST->value])
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }
}
