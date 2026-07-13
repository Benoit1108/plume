<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\Uid\UuidV4;

final class FollowUpId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw InvalidValue::because('FollowUpId cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** Généré par l'agrégat lui-même (auto-planification) — UUID v4 pur PHP. */
    public static function generate(): self
    {
        return new self(UuidV4::generate());
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
