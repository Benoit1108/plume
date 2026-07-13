<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Persistence\Doctrine;

use App\Account\Domain\Profile\Profile;
use App\Account\Domain\Profile\ProfileRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProfileRepository implements ProfileRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Profile $profile): void
    {
        $this->em->persist($profile);
        $this->em->flush();
    }

    public function find(TenantId $tenantId): ?Profile
    {
        // Requête (et non find()) pour que le filtre tenant s'applique — ceinture
        // et bretelles : la clé primaire EST déjà le tenant.
        $profile = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Profile::class, 'p')
            ->where('p.tenantId = :tenant')
            ->setParameter('tenant', $tenantId)
            ->getQuery()
            ->getOneOrNullResult();

        return $profile instanceof Profile ? $profile : null;
    }
}
