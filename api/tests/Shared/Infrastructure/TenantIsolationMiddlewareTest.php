<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use App\Shared\Infrastructure\Messenger\TenantIsolationMiddleware;
use App\Tests\Support\TenantRecordingMiddleware;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/** Isolation tenant côté worker (vrai TenantScope du conteneur, filtre Doctrine réel). */
final class TenantIsolationMiddlewareTest extends KernelTestCase
{
    private TenantContext $context;
    private TenantScope $scope;
    private TenantIsolationMiddleware $middleware;

    protected function setUp(): void
    {
        $c = static::getContainer();
        $context = $c->get(TenantContext::class);
        $scope = $c->get(TenantScope::class);
        \assert($context instanceof TenantContext);
        \assert($scope instanceof TenantScope);
        $this->context = $context;
        $this->scope = $scope;
        $this->middleware = new TenantIsolationMiddleware($scope);
        $this->scope->clear();
    }

    private function stackReturning(TenantRecordingMiddleware $next): StackInterface
    {
        $stack = $this->createStub(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }

    public function testWorkerConsumedTenantMessageActivatesDuringThenClearsAfter(): void
    {
        $recorder = new TenantRecordingMiddleware($this->context);
        $message = new class {
            public string $tenantId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        };

        $this->middleware->handle(new Envelope($message, [new ConsumedByWorkerStamp()]), $this->stackReturning($recorder));

        self::assertSame('cccccccc-cccc-cccc-cccc-cccccccccccc', $recorder->seen?->toString(), 'tenant du message actif PENDANT le handler');
        self::assertNull($this->context->get(), 'tenant remis à zéro APRÈS');
    }

    public function testWorkerConsumedMessageLeavesNoTenantLeak(): void
    {
        $this->context->set(TenantId::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')); // tenant d'un message précédent
        $recorder = new TenantRecordingMiddleware($this->context);

        $this->middleware->handle(new Envelope(new \stdClass(), [new ConsumedByWorkerStamp()]), $this->stackReturning($recorder));

        self::assertNull($recorder->seen, 'un message non tenanté ne réactive rien (ardoise propre)');
        self::assertNull($this->context->get(), 'pas de fuite après le message worker');
    }

    public function testInProcessDispatchKeepsTheRequestTenant(): void
    {
        $this->context->set(TenantId::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb')); // tenant de la requête HTTP
        $recorder = new TenantRecordingMiddleware($this->context);

        // Pas de ConsumedByWorkerStamp → dispatch synchrone : le tenant de la requête est préservé.
        $this->middleware->handle(new Envelope(new \stdClass()), $this->stackReturning($recorder));

        self::assertSame('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', $this->context->get()?->toString());
    }
}
