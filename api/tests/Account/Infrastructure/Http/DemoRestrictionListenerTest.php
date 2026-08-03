<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure\Http;

use App\Account\Infrastructure\Http\DemoRestrictionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Bridage des capacités du compte de démo (ROLE_DEMO) : boîte réelle + envoi d'emails réels refusés
 * (403 `demo_restricted`), tout le reste passe. Un utilisateur normal n'est jamais restreint.
 */
final class DemoRestrictionListenerTest extends TestCase
{
    public function testBlocksMailboxConnectForDemo(): void
    {
        $this->assertBlocked('/api/v1/mailbox/connect');
    }

    public function testBlocksMailboxOauthStartForDemo(): void
    {
        $this->assertBlocked('/api/v1/mailbox/oauth/start');
    }

    public function testBlocksRealSendForDemo(): void
    {
        $this->assertBlocked('/api/v1/drafts/9f/send');
    }

    public function testAllowsAiGenerationForDemo(): void
    {
        // La génération est autorisée (neutralisée séparément en canned) : la démo montre la rédaction.
        $this->expectNotToPerformAssertions();
        ($this->listener(['ROLE_DEMO']))($this->event('/api/v1/leads/abc/drafts'));
    }

    public function testDoesNotRestrictRegularUser(): void
    {
        $this->expectNotToPerformAssertions();
        ($this->listener(['ROLE_USER']))($this->event('/api/v1/mailbox/connect'));
    }

    private function assertBlocked(string $path): void
    {
        try {
            ($this->listener(['ROLE_DEMO']))($this->event($path));
            self::fail('Expected a 403 demo_restricted.');
        } catch (HttpException $e) {
            self::assertSame(Response::HTTP_FORBIDDEN, $e->getStatusCode());
            self::assertSame('demo_restricted', $e->getMessage());
        }
    }

    /** @param list<string> $roles */
    private function listener(array $roles): DemoRestrictionListener
    {
        $storage = new TokenStorage();
        $user = new InMemoryUser('demo', null, $roles);
        $storage->setToken(new UsernamePasswordToken($user, 'main', $roles));

        return new DemoRestrictionListener($storage);
    }

    private function event(string $path): ControllerEvent
    {
        return new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn (): Response => new Response(),
            Request::create($path, 'POST'),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
