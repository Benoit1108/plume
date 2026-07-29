<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Infrastructure\Http\MailSendRateLimitListener;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** Le plafond d'envoi (réputation de la boîte) : par tenant, uniquement sur POST /drafts/{id}/send. */
final class MailSendRateLimitListenerTest extends TestCase
{
    private MailSendRateLimitListener $listener;

    protected function setUp(): void
    {
        $factory = new RateLimiterFactory(
            ['id' => 'mailbox_send_test', 'policy' => 'sliding_window', 'limit' => 2, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
        $tenantContext = new TenantContext();
        $tenantContext->set(TenantId::fromString('0197b7e2-0000-7000-8000-000000000001'));
        $this->listener = new MailSendRateLimitListener($factory, $tenantContext);
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

    public function testLimitsSendRequestsPerTenant(): void
    {
        // Le motif matche quel que soit l'identifiant de brouillon.
        $this->dispatch('/api/v1/drafts/draft-1/send');
        $this->dispatch('/api/v1/drafts/draft-2/send');

        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/drafts/draft-3/send');
    }

    public function testOtherRoutesAndMethodsAreNotCounted(): void
    {
        // GET, sous-ressource différente et autres endpoints : jamais comptés.
        $this->dispatch('/api/v1/drafts/draft-1/send', 'GET');
        $this->dispatch('/api/v1/drafts/draft-1');
        $this->dispatch('/api/v1/drafts/draft-1/regenerate');

        // Le budget de 2 est intact.
        $this->dispatch('/api/v1/drafts/draft-1/send');
        $this->dispatch('/api/v1/drafts/draft-1/send');
        $this->expectException(TooManyRequestsHttpException::class);
        $this->dispatch('/api/v1/drafts/draft-1/send');
    }
}
