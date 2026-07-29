<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * La sonde de santé : 200 si la base répond, 503 sinon (la raison d'être de la sonde pour le
 * load-balancer — non couverte par le test fonctionnel happy-path).
 */
final class HealthControllerTest extends TestCase
{
    public function testReturns200WhenDatabaseResponds(): void
    {
        $connection = $this->createStub(Connection::class);
        $response = (new HealthController($connection))();

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"ok","db":"ok"}', (string) $response->getContent());
    }

    public function testReturns503WhenDatabaseIsDown(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('connection refused'));

        $response = (new HealthController($connection))();

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString('{"status":"degraded","db":"down"}', (string) $response->getContent());
    }
}
