<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Persistence\Doctrine;

use App\Mailbox\Domain\Mailbox\ConnectedMailbox;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineMailboxRepository implements MailboxRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(ConnectedMailbox $mailbox): void
    {
        $this->em->persist($mailbox);
        $this->em->flush();
    }

    public function findForTenant(TenantId $tenantId): ?ConnectedMailbox
    {
        // Requête explicite par tenant (fonctionne AUSSI hors HTTP — worker/scheduler,
        // où le SQLFilter est inactif) ; en HTTP le filtre s'applique en plus.
        $mailbox = $this->em->createQueryBuilder()
            ->select('m')
            ->from(ConnectedMailbox::class, 'm')
            ->where('m.tenantId = :tenant')
            ->setParameter('tenant', $tenantId)
            ->getQuery()
            ->getOneOrNullResult();

        return $mailbox instanceof ConnectedMailbox ? $mailbox : null;
    }
}
