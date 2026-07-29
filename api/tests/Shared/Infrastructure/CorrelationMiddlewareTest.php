<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Logging\CorrelationContext;
use App\Shared\Infrastructure\Messenger\CorrelationMiddleware;
use App\Shared\Infrastructure\Messenger\CorrelationStamp;
use App\Tests\Support\CorrelationRecordingMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/** Propagation de l'id de corrélation à travers le bus (envoi HTTP → worker). */
final class CorrelationMiddlewareTest extends TestCase
{
    private function stackReturning(MiddlewareInterface $next): StackInterface
    {
        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }

    public function testDispatchStampsTheCurrentRequestId(): void
    {
        $context = new CorrelationContext();
        $context->set('req-1');
        $recorder = new CorrelationRecordingMiddleware($context);

        (new CorrelationMiddleware($context))->handle(new Envelope(new \stdClass()), $this->stackReturning($recorder));

        $stamp = $recorder->received?->last(CorrelationStamp::class);
        self::assertInstanceOf(CorrelationStamp::class, $stamp);
        self::assertSame('req-1', $stamp->requestId);
    }

    public function testDispatchWithoutCorrelationAddsNoStamp(): void
    {
        $context = new CorrelationContext();
        $recorder = new CorrelationRecordingMiddleware($context);

        (new CorrelationMiddleware($context))->handle(new Envelope(new \stdClass()), $this->stackReturning($recorder));

        self::assertNull($recorder->received?->last(CorrelationStamp::class));
    }

    public function testDispatchDoesNotOverwriteAnExistingStamp(): void
    {
        $context = new CorrelationContext();
        $context->set('req-new');
        $recorder = new CorrelationRecordingMiddleware($context);

        $envelope = new Envelope(new \stdClass(), [new CorrelationStamp('req-original')]);
        (new CorrelationMiddleware($context))->handle($envelope, $this->stackReturning($recorder));

        self::assertSame('req-original', $recorder->received?->last(CorrelationStamp::class)?->requestId);
    }

    public function testWorkerConsumeActivatesThenClearsTheStampedId(): void
    {
        $context = new CorrelationContext();
        $recorder = new CorrelationRecordingMiddleware($context);

        $envelope = new Envelope(new \stdClass(), [new ConsumedByWorkerStamp(), new CorrelationStamp('req-2')]);
        (new CorrelationMiddleware($context))->handle($envelope, $this->stackReturning($recorder));

        self::assertSame('req-2', $recorder->seen, 'corrélation active PENDANT le handler worker');
        self::assertNull($context->get(), 'corrélation remise à zéro APRÈS');
    }
}
