<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Infrastructure;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Sourcing\Infrastructure\Http\SourcePollRateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** Le plafond de relève manuelle (I/O réseau sortant) : par tenant, uniquement sur POST /sources/poll. */
final class SourcePollRateLimitListenerTest extends TestCase
{
    private SourcePollRateLimitListener $listener;

    protected function setUp(): void
    {
        $factory = new RateLimiterFactory(
            ['id' => 'sources_poll_test', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
        $tenantContext = new TenantContext();
        $tenantContext->set(TenantId::fromString('0197b7e2-0000-7000-8000-000000000001'));
        $this->listener = new SourcePollRateLimitListener($factory, $tenantContext);
    }

    private function dispatch(string $path, string $method = 'POST'): void
    {
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path, $method),
            HttpKernelInterface::MAIN_REQUEST,
        );
        ($this->listener)($event);
    }

    public function testLimitsPollRequestsPerTenant(): void
    {
        $this->dispatch('/api/v1/sources/poll');
        $this->dispatch('/api/v1/sources/poll');

        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/sources/poll');
    }

    public function testOtherRoutesAndMethodsAreNotCounted(): void
    {
        // GET sur le même chemin + autres endpoints : jamais comptés.
        $this->dispatch('/api/v1/sources/poll', 'GET');
        $this->dispatch('/api/v1/sources');
        $this->dispatch('/api/v1/leads/lead-1/drafts');

        // Le budget de 2 est intact.
        $this->dispatch('/api/v1/sources/poll');
        $this->dispatch('/api/v1/sources/poll');
        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/sources/poll');
    }
}
