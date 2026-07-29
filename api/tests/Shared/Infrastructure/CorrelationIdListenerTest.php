<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\CorrelationIdListener;
use App\Shared\Infrastructure\Logging\CorrelationContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** L'attribution d'un id de corrélation par requête (génération, réutilisation sûre, en-tête). */
final class CorrelationIdListenerTest extends TestCase
{
    private function fire(CorrelationIdListener $listener, Request $request): void
    {
        $listener->onRequest(new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        ));
    }

    public function testGeneratesAUuidWhenNoIncomingHeader(): void
    {
        $context = new CorrelationContext();
        $this->fire(new CorrelationIdListener($context), Request::create('/api/v1/leads'));

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) $context->get());
    }

    public function testReusesASafeIncomingHeader(): void
    {
        $context = new CorrelationContext();
        $request = Request::create('/api/v1/leads');
        $request->headers->set('X-Request-Id', 'trace-abc_123.4');

        $this->fire(new CorrelationIdListener($context), $request);

        self::assertSame('trace-abc_123.4', $context->get());
    }

    public function testRejectsAnUnsafeIncomingHeaderAndGeneratesInstead(): void
    {
        $context = new CorrelationContext();
        $request = Request::create('/api/v1/leads');
        // Injection de log / caractères interdits → on ne fait pas confiance, on génère.
        $request->headers->set('X-Request-Id', "evil id\nInjected: 1");

        $this->fire(new CorrelationIdListener($context), $request);

        self::assertNotSame("evil id\nInjected: 1", $context->get());
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', (string) $context->get());
    }

    public function testEchoesTheIdInTheResponseHeader(): void
    {
        $context = new CorrelationContext();
        $context->set('req-xyz');
        $listener = new CorrelationIdListener($context);
        $response = new Response();

        $listener->onResponse(new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        ));

        self::assertSame('req-xyz', $response->headers->get('X-Request-Id'));
    }
}
