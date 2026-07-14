<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Persistence\Doctrine;

use App\Drafting\Domain\Template\Exception\TemplateNotFound;
use App\Drafting\Domain\Template\Template;
use App\Drafting\Domain\Template\TemplateId;
use App\Drafting\Domain\Template\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTemplateRepository implements TemplateRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Template $template): void
    {
        $this->em->persist($template);
        $this->em->flush();
    }

    public function get(TemplateId $id): Template
    {
        $template = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Template::class, 't')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$template instanceof Template) {
            throw TemplateNotFound::withId($id);
        }

        return $template;
    }

    public function remove(Template $template): void
    {
        $this->em->remove($template);
        $this->em->flush();
    }

    public function count(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Template::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
