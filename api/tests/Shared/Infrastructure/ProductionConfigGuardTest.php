<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\ProductionConfigGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * La garde de prod refuse de servir tant qu'un secret critique reste sur le placeholder de dev
 * (ou vide), et laisse passer dès que de vrais secrets sont fournis.
 */
final class ProductionConfigGuardTest extends TestCase
{
    private function event(): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST);
    }

    public function testRejectsPlaceholderAppSecret(): void
    {
        $guard = new ProductionConfigGuard('change_me_in_env_local', 'a-real-passphrase', 'a-real-db-password');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_SECRET/');
        $guard($this->event());
    }

    public function testRejectsEmptyJwtPassphrase(): void
    {
        $guard = new ProductionConfigGuard('a-real-secret', '', 'a-real-db-password');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/JWT_PASSPHRASE/');
        $guard($this->event());
    }

    public function testRejectsDefaultDbPassword(): void
    {
        $guard = new ProductionConfigGuard('a-real-secret', 'a-real-passphrase', 'plume_app');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_DB_PASSWORD/');
        $guard($this->event());
    }

    public function testPassesWithRealSecrets(): void
    {
        $guard = new ProductionConfigGuard('a-real-strong-secret', 'a-real-strong-passphrase', 'a-real-strong-db-password');

        $guard($this->event());
        $this->addToAssertionCount(1);
    }

    public function testIgnoresSubRequests(): void
    {
        $guard = new ProductionConfigGuard('change_me_in_env_local', '', 'plume_app');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $subRequest = new RequestEvent($kernel, new Request(), HttpKernelInterface::SUB_REQUEST);

        $guard($subRequest); // placeholder mais sous-requête → aucune vérif, pas d'exception
        $this->addToAssertionCount(1);
    }
}
