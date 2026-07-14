<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound;

enum OutboundStatus: string
{
    case SENDING = 'SENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}
