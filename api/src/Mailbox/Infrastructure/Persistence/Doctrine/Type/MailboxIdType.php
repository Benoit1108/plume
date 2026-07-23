<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Persistence\Doctrine\Type;

use App\Mailbox\Domain\Mailbox\MailboxId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;

/** Type DBAL pour le VO MailboxId (persisté en chaîne). */
final class MailboxIdType extends AbstractStringIdType
{
    public const string NAME = 'mailbox_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return MailboxId::class;
    }
}
