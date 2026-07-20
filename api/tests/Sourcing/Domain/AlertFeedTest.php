<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Domain;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\AlertFeed\AlertFeed;
use App\Sourcing\Domain\AlertFeed\AlertFeedId;
use App\Sourcing\Domain\AlertFeed\Event\AlertFeedActivationChanged;
use App\Sourcing\Domain\AlertFeed\Event\AlertFeedAdded;
use App\Sourcing\Domain\CandidateLead\Source;
use PHPUnit\Framework\TestCase;

final class AlertFeedTest extends TestCase
{
    private const NOW = '2026-07-20 10:00:00';

    private function add(string $url = 'https://proz.example/rss', ?string $label = null): AlertFeed
    {
        return AlertFeed::add(
            AlertFeedId::fromString('feed-1'),
            TenantId::fromString('tenant-1'),
            Source::RSS,
            $url,
            $label,
            new \DateTimeImmutable(self::NOW),
        );
    }

    public function testAddCreatesActiveFeedAndRecordsEvent(): void
    {
        $feed = $this->add();

        self::assertTrue($feed->isActive());
        self::assertSame('https://proz.example/rss', $feed->url());
        self::assertInstanceOf(AlertFeedAdded::class, $feed->pullDomainEvents()[0]);
    }

    public function testLabelDefaultsToHostWhenBlank(): void
    {
        self::assertSame('proz.example', $this->add('https://proz.example/rss', '  ')->label());
    }

    public function testCustomLabelIsKept(): void
    {
        self::assertSame('ProZ FR', $this->add('https://proz.example/rss', 'ProZ FR')->label());
    }

    public function testInvalidUrlIsRejected(): void
    {
        $this->expectException(InvalidValue::class);
        $this->add('pas-une-url');
    }

    public function testNonHttpUrlIsRejected(): void
    {
        $this->expectException(InvalidValue::class);
        $this->add('ftp://proz.example/rss');
    }

    public function testDeactivateTogglesAndRecordsEvent(): void
    {
        $feed = $this->add();
        $feed->pullDomainEvents();

        $feed->setActive(false, new \DateTimeImmutable(self::NOW));

        self::assertFalse($feed->isActive());
        $event = $feed->pullDomainEvents()[0];
        self::assertInstanceOf(AlertFeedActivationChanged::class, $event);
        self::assertFalse($event->active);
    }

    public function testSettingSameActivationIsANoOp(): void
    {
        $feed = $this->add();
        $feed->pullDomainEvents();

        $feed->setActive(true, new \DateTimeImmutable(self::NOW)); // déjà actif

        self::assertCount(0, $feed->pullDomainEvents());
    }
}
