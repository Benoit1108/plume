<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound;

use App\Shared\Domain\Exception\InvalidValue;

final class OutboundMessageId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw InvalidValue::because('OutboundMessageId cannot be empty.');
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

    public function __toString(): string
    {
        return $this->value;
    }
}
