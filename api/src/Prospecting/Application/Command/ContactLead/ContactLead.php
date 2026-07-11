<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\ContactLead;

use App\Shared\Application\Command\Command;

final class ContactLead implements Command
{
    public function __construct(public readonly string $leadId)
    {
    }
}
