<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadAccepted;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadIngested;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadMerged;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadRejected;
use App\Sourcing\Domain\CandidateLead\Exception\CandidateAlreadyTriaged;

/**
 * Annonce ingérée en attente de tri (contexte Sourcing — ADR-0020).
 * Ce n'est PAS une Piste : elle vit ici jusqu'à la décision humaine
 * (accepter / fusionner / rejeter). Immuable une fois triée (garde contre
 * le double-clic / la redélivrance — leçon P0 fin M1).
 */
final class CandidateLead extends AggregateRoot
{
    private ?string $promotedLeadId = null;
    private ?string $organizationId = null;

    private function __construct(
        private readonly CandidateLeadId $id,
        private readonly TenantId $tenantId,
        private readonly Source $source,
        private readonly string $dedupHash,
        private CandidateStatus $status,
        private readonly string $title,
        private readonly ?string $organizationName,
        private readonly ?string $languagePair,
        private readonly ?string $url,
        private readonly ?string $excerpt,
        private readonly ?\DateTimeImmutable $postedAt,
        private readonly \DateTimeImmutable $ingestedAt,
        private readonly ?string $rawRef = null,
    ) {
    }

    public static function ingest(
        CandidateLeadId $id,
        TenantId $tenantId,
        Source $source,
        string $dedupHash,
        string $title,
        ?string $organizationName,
        ?string $languagePair,
        ?string $url,
        ?string $excerpt,
        ?\DateTimeImmutable $postedAt,
        \DateTimeImmutable $now,
        ?string $rawRef = null,
    ): self {
        $title = trim($title);
        if ('' === $title) {
            throw InvalidValue::because('Candidate title cannot be empty.');
        }
        // Invariant aligné sur la colonne `title` VARCHAR(300) : jamais d'INSERT en échec
        // (les parsers tronquent en amont ; garde de dernier recours ici).
        if (mb_strlen($title) > 300) {
            throw InvalidValue::because('Candidate title is too long (max 300).');
        }
        if ('' === trim($dedupHash)) {
            throw InvalidValue::because('Candidate dedupHash cannot be empty.');
        }

        $candidate = new self(
            $id,
            $tenantId,
            $source,
            $dedupHash,
            CandidateStatus::PENDING,
            $title,
            self::normalize($organizationName),
            self::normalize($languagePair),
            self::normalize($url),
            self::normalize($excerpt),
            $postedAt,
            $now,
            self::normalize($rawRef),
        );
        $candidate->recordEvent(new CandidateLeadIngested(
            $id->toString(),
            $tenantId->toString(),
            $source->value,
            $dedupHash,
            $now,
        ));

        return $candidate;
    }

    /** Promotion : nouvelle organisation + piste créées (le handler les a fabriquées). */
    public function accept(string $leadId, string $organizationId, \DateTimeImmutable $now): void
    {
        $this->ensurePending();
        $this->status = CandidateStatus::ACCEPTED;
        $this->promotedLeadId = $leadId;
        $this->organizationId = $organizationId;
        $this->recordEvent(new CandidateLeadAccepted($this->id->toString(), $this->tenantId->toString(), $leadId, $organizationId, $now));
    }

    /** Rattachement à une organisation existante (résolution d'un doublon) + piste créée. */
    public function merge(string $leadId, string $organizationId, \DateTimeImmutable $now): void
    {
        $this->ensurePending();
        $this->status = CandidateStatus::MERGED;
        $this->promotedLeadId = $leadId;
        $this->organizationId = $organizationId;
        $this->recordEvent(new CandidateLeadMerged($this->id->toString(), $this->tenantId->toString(), $leadId, $organizationId, $now));
    }

    public function reject(\DateTimeImmutable $now): void
    {
        $this->ensurePending();
        $this->status = CandidateStatus::REJECTED;
        $this->recordEvent(new CandidateLeadRejected($this->id->toString(), $this->tenantId->toString(), $now));
    }

    private function ensurePending(): void
    {
        if (CandidateStatus::PENDING !== $this->status) {
            throw CandidateAlreadyTriaged::is($this->status);
        }
    }

    private static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    public function id(): CandidateLeadId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function source(): Source
    {
        return $this->source;
    }

    public function dedupHash(): string
    {
        return $this->dedupHash;
    }

    public function status(): CandidateStatus
    {
        return $this->status;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function organizationName(): ?string
    {
        return $this->organizationName;
    }

    public function languagePair(): ?string
    {
        return $this->languagePair;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function excerpt(): ?string
    {
        return $this->excerpt;
    }

    public function postedAt(): ?\DateTimeImmutable
    {
        return $this->postedAt;
    }

    public function promotedLeadId(): ?string
    {
        return $this->promotedLeadId;
    }

    public function organizationId(): ?string
    {
        return $this->organizationId;
    }

    public function ingestedAt(): \DateTimeImmutable
    {
        return $this->ingestedAt;
    }

    /** Référence vers le brut conservé (`RawAlert`), null pour la saisie manuelle. */
    public function rawRef(): ?string
    {
        return $this->rawRef;
    }
}
