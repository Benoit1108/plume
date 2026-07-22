<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/** Middleware de test : mémorise le tenant courant AU MOMENT où le handler s'exécute. */
final class TenantRecordingMiddleware implements MiddlewareInterface
{
    public ?TenantId $seen = null;

    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->seen = $this->context->get();

        return $envelope;
    }
}
