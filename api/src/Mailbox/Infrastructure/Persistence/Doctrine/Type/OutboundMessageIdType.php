<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Persistence\Doctrine\Type;

use App\Mailbox\Domain\Outbound\OutboundMessageId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;

/** Type DBAL pour le VO OutboundMessageId (persisté en chaîne). */
final class OutboundMessageIdType extends AbstractStringIdType
{
    public const string NAME = 'outbound_message_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return OutboundMessageId::class;
    }
}
