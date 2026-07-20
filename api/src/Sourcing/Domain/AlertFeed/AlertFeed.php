<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\AlertFeed\Event\AlertFeedActivationChanged;
use App\Sourcing\Domain\AlertFeed\Event\AlertFeedAdded;
use App\Sourcing\Domain\AlertFeed\Event\AlertFeedRemoved;
use App\Sourcing\Domain\CandidateLead\Source;

/**
 * Flux d'annonces configuré par le tenant (M3.1b) : l'URL d'une source (RSS) que la relève
 * interroge. Seuls les flux `active` sont relevés.
 */
final class AlertFeed extends AggregateRoot
{
    private const int MAX_URL = 2000;
    private const int MAX_LABEL = 120;

    private function __construct(
        private readonly AlertFeedId $id,
        private readonly TenantId $tenantId,
        private readonly Source $source,
        private readonly string $url,
        private string $label,
        private bool $active,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function add(
        AlertFeedId $id,
        TenantId $tenantId,
        Source $source,
        string $url,
        ?string $label,
        \DateTimeImmutable $now,
    ): self {
        $url = trim($url);
        if (false === filter_var($url, \FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw InvalidValue::because('Alert feed URL must be a valid http(s) URL.');
        }
        if (mb_strlen($url) > self::MAX_URL) {
            throw InvalidValue::because('Alert feed URL is too long.');
        }

        $label = trim((string) $label);
        $label = '' === $label ? self::defaultLabel($url) : mb_substr($label, 0, self::MAX_LABEL);

        $feed = new self($id, $tenantId, $source, $url, $label, true, $now);
        $feed->recordEvent(new AlertFeedAdded($id->toString(), $tenantId->toString(), $source->value, $url, $now));

        return $feed;
    }

    public function setActive(bool $active, \DateTimeImmutable $now): void
    {
        if ($active === $this->active) {
            return;
        }
        $this->active = $active;
        $this->recordEvent(new AlertFeedActivationChanged($this->id->toString(), $this->tenantId->toString(), $active, $now));
    }

    /** Événement de retrait (le repository supprime la ligne après publication). */
    public function markRemoved(\DateTimeImmutable $now): void
    {
        $this->recordEvent(new AlertFeedRemoved($this->id->toString(), $this->tenantId->toString(), $now));
    }

    private static function defaultLabel(string $url): string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? $host : mb_substr($url, 0, self::MAX_LABEL);
    }

    public function id(): AlertFeedId
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

    public function url(): string
    {
        return $this->url;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
