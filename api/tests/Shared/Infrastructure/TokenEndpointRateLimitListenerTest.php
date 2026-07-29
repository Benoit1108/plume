<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\TokenEndpointRateLimitListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** Anti force brute sur les endpoints token publics : par IP, avant le firewall. */
final class TokenEndpointRateLimitListenerTest extends TestCase
{
    private TokenEndpointRateLimitListener $listener;

    protected function setUp(): void
    {
        $factory = new RateLimiterFactory(
            ['id' => 'token_endpoints_test', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
        $this->listener = new TokenEndpointRateLimitListener($factory);
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

    public function testLimitsTokenEndpointsByIp(): void
    {
        // Le budget est partagé entre refresh et invalidate (même IP).
        $this->dispatch('/api/v1/token/refresh');
        $this->dispatch('/api/v1/token/invalidate');

        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/token/refresh');
    }

    public function testOtherRoutesAndMethodsAreNotCounted(): void
    {
        // GET sur les endpoints token + autres chemins : jamais comptés.
        $this->dispatch('/api/v1/token/refresh', 'GET');
        $this->dispatch('/api/v1/login_check');
        $this->dispatch('/api/v1/register');

        // Le budget de 2 est intact.
        $this->dispatch('/api/v1/token/refresh');
        $this->dispatch('/api/v1/token/invalidate');
        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/token/invalidate');
    }
}
