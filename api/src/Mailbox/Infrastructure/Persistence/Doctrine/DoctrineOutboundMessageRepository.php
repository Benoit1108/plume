<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Persistence\Doctrine;

use App\Mailbox\Domain\Outbound\Exception\OutboundMessageNotFound;
use App\Mailbox\Domain\Outbound\OutboundMessage;
use App\Mailbox\Domain\Outbound\OutboundMessageId;
use App\Mailbox\Domain\Outbound\OutboundMessageRepository;
use App\Mailbox\Domain\Outbound\OutboundStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOutboundMessageRepository implements OutboundMessageRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(OutboundMessage $message): void
    {
        $this->em->persist($message);
        $this->em->flush();
    }

    public function get(OutboundMessageId $id): OutboundMessage
    {
        // QueryBuilder (pas find()) : le filtre tenant s'applique en HTTP ;
        // dans le worker, le handler vérifie le tenant de la commande.
        $message = $this->em->createQueryBuilder()
            ->select('m')
            ->from(OutboundMessage::class, 'm')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$message instanceof OutboundMessage) {
            throw OutboundMessageNotFound::withId($id->toString());
        }

        return $message;
    }

    public function existsActiveForDraft(string $tenantId, string $draftId): bool
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(OutboundMessage::class, 'm')
            ->where('m.tenantId = :tenant')
            ->andWhere('m.draftId = :draft')
            ->andWhere('m.status != :failed')
            ->setParameter('tenant', \App\Shared\Domain\ValueObject\TenantId::fromString($tenantId))
            ->setParameter('draft', $draftId)
            ->setParameter('failed', OutboundStatus::FAILED->value)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
