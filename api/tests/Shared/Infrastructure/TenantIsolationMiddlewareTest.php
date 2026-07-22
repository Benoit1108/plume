<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use App\Shared\Infrastructure\Messenger\TenantIsolationMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

final class TenantIsolationMiddlewareTest extends TestCase
{
    private function middleware(TenantContext $context): TenantIsolationMiddleware
    {
        $filters = $this->createStub(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false); // clear() ne tentera pas de disable
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        return new TenantIsolationMiddleware(new TenantScope($context, $em));
    }

    private function passThroughStack(): StackInterface
    {
        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn(new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope;
            }
        });

        return $stack;
    }

    public function testWorkerConsumedMessageLeavesNoTenantLeak(): void
    {
        $context = new TenantContext();
        $context->set(TenantId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')); // tenant d'un message précédent

        // Message reçu d'un transport (worker).
        $envelope = new Envelope(new \stdClass(), [new ConsumedByWorkerStamp()]);
        $this->middleware($context)->handle($envelope, $this->passThroughStack());

        self::assertNull($context->get(), 'le tenant est remis à zéro après un message worker');
    }

    public function testInProcessDispatchKeepsTheRequestTenant(): void
    {
        $context = new TenantContext();
        $context->set(TenantId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb')); // tenant de la requête HTTP

        // Dispatch synchrone (pas de ReceivedStamp) : le tenant de la requête ne doit PAS être effacé.
        $envelope = new Envelope(new \stdClass());
        $this->middleware($context)->handle($envelope, $this->passThroughStack());

        self::assertSame('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', $context->get()?->toString());
    }
}
