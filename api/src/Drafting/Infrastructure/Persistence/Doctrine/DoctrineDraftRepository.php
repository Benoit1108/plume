<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Persistence\Doctrine;

use App\Drafting\Domain\Draft\Draft;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftRepository;
use App\Drafting\Domain\Draft\Exception\DraftNotFound;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineDraftRepository implements DraftRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Draft $draft): void
    {
        $this->em->persist($draft);
        $this->em->flush();
    }

    public function get(DraftId $id): Draft
    {
        // Requête (et non find()) pour que le filtre tenant s'applique en HTTP.
        // Dans le worker (filtre inactif), l'id provient de nos propres events : sûr.
        $draft = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Draft::class, 'd')
            ->where('d.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$draft instanceof Draft) {
            throw DraftNotFound::withId($id);
        }

        return $draft;
    }

    public function remove(Draft $draft): void
    {
        $this->em->remove($draft);
        $this->em->flush();
    }
}
