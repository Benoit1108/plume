<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Logging\CorrelationContext;
use App\Shared\Infrastructure\Logging\CorrelationIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/** Le processor n'ajoute `request_id` que lorsqu'une corrélation est active. */
final class CorrelationIdProcessorTest extends TestCase
{
    private function record(): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable('2026-07-29 10:00:00'), 'app', Level::Info, 'msg');
    }

    public function testAddsRequestIdWhenSet(): void
    {
        $context = new CorrelationContext();
        $context->set('req-123');

        $out = (new CorrelationIdProcessor($context))($this->record());

        self::assertSame('req-123', $out->extra['request_id'] ?? null);
    }

    public function testAddsNothingWhenNoCorrelation(): void
    {
        $out = (new CorrelationIdProcessor(new CorrelationContext()))($this->record());

        self::assertArrayNotHasKey('request_id', $out->extra);
    }
}
