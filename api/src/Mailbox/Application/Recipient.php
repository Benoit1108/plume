<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

final class Recipient
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $name,
        public readonly bool $contactAllowed,
    ) {
    }
}
