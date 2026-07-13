<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine;

use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOrganizationRepository implements OrganizationRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Organization $organization): void
    {
        $this->em->persist($organization);
        $this->em->flush();
    }

    public function get(OrganizationId $id): Organization
    {
        return $this->em->find(Organization::class, $id)
            ?? throw new \RuntimeException(sprintf('Organization "%s" not found.', $id->toString()));
    }
}
