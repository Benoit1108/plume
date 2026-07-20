<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Sourcing\Infrastructure\ApiResource\State\PollSourcesProcessor;

/**
 * Sources d'annonces. M3.1a : un seul geste — relever la source configurée pour le tenant
 * courant (déclenchement manuel). La gestion des flux (CRUD) et le Scheduler auto = M3.1b.
 */
#[ApiResource(
    shortName: 'Source',
    operations: [
        new Post(
            uriTemplate: '/sources/poll',
            name: 'sources_poll',
            input: false,
            read: false,
            output: false,
            status: 202,
            processor: PollSourcesProcessor::class,
            openapi: new Operation(summary: 'Relever la source configurée (tenant courant) et ingérer les annonces trouvées'),
        ),
    ],
)]
final class SourceResource
{
}
