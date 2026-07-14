<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox;

use App\Shared\Domain\Exception\InvalidValue;

final class MailboxId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw InvalidValue::because('MailboxId cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
