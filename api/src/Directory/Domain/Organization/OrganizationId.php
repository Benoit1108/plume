<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization;

final class OrganizationId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('OrganizationId cannot be empty.');
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
