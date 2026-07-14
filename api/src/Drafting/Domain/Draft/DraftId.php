<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft;

use App\Shared\Domain\Exception\InvalidValue;

final class DraftId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw InvalidValue::because('DraftId cannot be empty.');
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
