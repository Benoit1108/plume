<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine;

use App\Directory\Domain\Organization\Exception\OrganizationNotFound;
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

    public function isNameTaken(string $name, ?OrganizationId $ignore = null): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Organization::class, 'o')
            ->where('LOWER(o.name) = :name')
            ->setParameter('name', mb_strtolower(trim($name)));

        if (null !== $ignore) {
            $qb->andWhere('o.id != :ignore')->setParameter('ignore', $ignore);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    public function takenNamesAmong(array $names): array
    {
        $normalized = array_values(array_unique(array_map(
            static fn (string $name): string => mb_strtolower(trim($name)),
            $names,
        )));
        if ([] === $normalized) {
            return [];
        }

        /** @var list<array{name: string}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('o.name AS name')
            ->from(Organization::class, 'o')
            ->where('LOWER(o.name) IN (:names)')
            ->setParameter('names', $normalized)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => mb_strtolower(trim($row['name'])), $rows);
    }
}
