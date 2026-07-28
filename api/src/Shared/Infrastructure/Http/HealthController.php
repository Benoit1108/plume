<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Sonde de santé (V2.1a) pour l'hébergeur / le monitoring / le load-balancer. PUBLIC, minimale :
 * vérifie la connectivité base (le service ne vaut rien sans elle). 200 si tout va, 503 sinon.
 * Ne divulgue aucun détail interne (pas de version, pas de trace). N'exige pas de tenant.
 */
#[AsController]
final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(): Response
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $db = true;
        } catch (\Throwable) {
            $db = false;
        }

        return new JsonResponse(
            ['status' => $db ? 'ok' : 'degraded', 'db' => $db ? 'ok' : 'down'],
            $db ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
