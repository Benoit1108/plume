<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\Doctrine;

use App\Prospecting\Domain\Lead\Lead;
use App\Prospecting\Domain\Lead\LeadId;
use App\Prospecting\Domain\Lead\LeadRepository;
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
        return $this->em->find(Lead::class, $id)
            ?? throw new \RuntimeException(sprintf('Lead "%s" not found.', $id->toString()));
    }
}
