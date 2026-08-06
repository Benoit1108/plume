<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\RlsRuntimeRoleGuard;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * La garde refuse de servir si l'API tourne sous un rôle qui CONTOURNE la RLS (superuser ou
 * bypassrls) : sans elle, l'isolation multi-tenant en base serait absente SILENCIEUSEMENT.
 */
final class RlsRuntimeRoleGuardTest extends TestCase
{
    private function event(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }

    /** @param array{rolname: string, privileged: bool}|false $role */
    private function guard(array|false $role): RlsRuntimeRoleGuard
    {
        // Stub (et non mock) : on n'assert aucun appel ici — cf. failOnNotice du phpunit.dist.xml.
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($role);

        return new RlsRuntimeRoleGuard($connection, new NullLogger());
    }

    public function testRejectsSuperuserOrBypassRlsRole(): void
    {
        $guard = $this->guard(['rolname' => 'plume', 'privileged' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/plume.*Row-Level Security is inert/s');
        $guard($this->event());
    }

    public function testPassesWithNonOwnerRuntimeRole(): void
    {
        $guard = $this->guard(['rolname' => 'plume_app', 'privileged' => false]);

        $guard($this->event());
        $this->addToAssertionCount(1);
    }

    public function testIgnoresSubRequests(): void
    {
        $guard = $this->guard(['rolname' => 'plume', 'privileged' => true]);

        $guard($this->event(HttpKernelInterface::SUB_REQUEST)); // privilégié mais sous-requête
        $this->addToAssertionCount(1);
    }

    public function testDoesNotBlockWhenDatabaseIsUnreachable(): void
    {
        // Une base injoignable est une PANNE, pas une faille de configuration : la garde ne doit pas
        // la transformer en erreur cryptique (la requête échouera de toute façon plus loin).
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willThrowException(new \RuntimeException('connection refused'));
        $guard = new RlsRuntimeRoleGuard($connection, new NullLogger());

        $guard($this->event());
        $this->addToAssertionCount(1);
    }

    public function testVerifiesOnlyOncePerProcess(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')
            ->willReturn(['rolname' => 'plume_app', 'privileged' => false]);
        $guard = new RlsRuntimeRoleGuard($connection, new NullLogger());

        $guard($this->event());
        $guard($this->event()); // 2e requête : aucune requête SQL supplémentaire
        $this->addToAssertionCount(1);
    }
}
