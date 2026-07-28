<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

/**
 * La sonde de santé répond 200 avec la base joignable, sans authentification (monitoring/LB).
 */
final class HealthApiTest extends ApiTestCase
{
    public function testHealthIsPublicAndReportsOk(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/health');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['status' => 'ok', 'db' => 'ok'], $response->toArray());
    }
}
