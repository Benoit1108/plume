<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound;

interface OutboundMessageRepository
{
    public function save(OutboundMessage $message): void;

    /** @throws Exception\OutboundMessageNotFound */
    public function get(OutboundMessageId $id): OutboundMessage;
}
