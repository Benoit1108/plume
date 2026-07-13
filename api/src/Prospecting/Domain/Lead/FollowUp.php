<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/**
 * Relance — entité DANS l'agrégat Lead (collection JSONB, cf. ADR-0012).
 * Immuable : les changements d'état produisent une nouvelle instance
 * (nécessaire pour que Doctrine détecte la mutation de la collection JSON).
 */
final class FollowUp
{
    public function __construct(
        private readonly FollowUpId $id,
        private readonly \DateTimeImmutable $dueAt,
        private readonly ?string $label,
        private readonly FollowUpStatus $status,
    ) {
    }

    public static function pending(FollowUpId $id, \DateTimeImmutable $dueAt, ?string $label): self
    {
        return new self($id, $dueAt, $label, FollowUpStatus::PENDING);
    }

    public function done(): self
    {
        return new self($this->id, $this->dueAt, $this->label, FollowUpStatus::DONE);
    }

    public function cancelled(): self
    {
        return new self($this->id, $this->dueAt, $this->label, FollowUpStatus::CANCELLED);
    }

    public function id(): FollowUpId
    {
        return $this->id;
    }

    public function dueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    public function status(): FollowUpStatus
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return FollowUpStatus::PENDING === $this->status;
    }
}
