<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

final class LeadId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw new \InvalidArgumentException('LeadId cannot be empty.');
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
}
