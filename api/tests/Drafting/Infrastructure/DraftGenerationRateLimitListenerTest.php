<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Infrastructure;

use App\Drafting\Infrastructure\Http\DraftGenerationRateLimitListener;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** Le plafond de génération : par tenant, uniquement sur les routes de génération. */
final class DraftGenerationRateLimitListenerTest extends TestCase
{
    private DraftGenerationRateLimitListener $listener;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $factory = new RateLimiterFactory(
            ['id' => 'drafting_generation_test', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
        $this->tenantContext = new TenantContext();
        $this->tenantContext->set(TenantId::fromString('0197b7e2-0000-7000-8000-000000000001'));
        $this->listener = new DraftGenerationRateLimitListener($factory, $this->tenantContext);
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

    public function testLimitsGenerationRequestsPerTenant(): void
    {
        $this->dispatch('/api/v1/leads/lead-1/drafts');
        $this->dispatch('/api/v1/drafts/draft-1/regenerate');

        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/leads/lead-1/drafts');
    }

    public function testOtherRoutesAndMethodsAreNotCounted(): void
    {
        // GET sur les mêmes chemins + autres endpoints : jamais comptés.
        $this->dispatch('/api/v1/leads/lead-1/drafts', 'GET');
        $this->dispatch('/api/v1/drafts/draft-1', 'PATCH');
        $this->dispatch('/api/v1/templates');
        $this->dispatch('/api/v1/leads/lead-1/contact');

        // Le budget de 2 est intact : les deux POST légitimes passent, le 3e sature.
        $this->dispatch('/api/v1/leads/lead-1/drafts');
        $this->dispatch('/api/v1/leads/lead-1/drafts');
        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/leads/lead-1/drafts');
    }
}
