<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Infrastructure\Logging\CorrelationContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/** Middleware de test : mémorise l'enveloppe reçue + la corrélation active au moment du handler. */
final class CorrelationRecordingMiddleware implements MiddlewareInterface
{
    public ?Envelope $received = null;
    public ?string $seen = null;

    public function __construct(private readonly CorrelationContext $context)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->received = $envelope;
        $this->seen = $this->context->get();

        return $envelope;
    }
}
