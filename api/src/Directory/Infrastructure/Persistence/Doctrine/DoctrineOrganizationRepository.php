<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine;

use App\Directory\Domain\Organization\Exception\OrganizationNotFound;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Directory\Domain\Organization\OrganizationType;
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
        // Requête (et non find()) pour que le filtre tenant s'applique -> isolation sur GET/PATCH.
        $organization = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Organization::class, 'o')
            ->where('o.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$organization instanceof Organization) {
            throw OrganizationNotFound::withId($id);
        }

        return $organization;
    }

    public function findMatching(?OrganizationType $type, ?string $query): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Organization::class, 'o')
            ->orderBy('o.name', 'ASC');

        if (null !== $type) {
            $qb->andWhere('o.type = :type')->setParameter('type', $type);
        }
        if (null !== $query && '' !== trim($query)) {
            $qb->andWhere('LOWER(o.name) LIKE :q')->setParameter('q', '%'.strtolower(trim($query)).'%');
        }

        /** @var Organization[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
