<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\PollAlertSource;

use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Command\CommandHandler;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidate;
use App\Sourcing\Application\Source\AlertSource;

/**
 * Relève : chaque `ParsedAlert` devient une commande `IngestCandidate` (dispatch imbriqué,
 * même transaction). Le dédoublonnage vit dans `IngestCandidate` (no-op si déjà vue) ;
 * le parser a déjà écarté les items malformés — pas de propagation d'erreur par item.
 */
final class PollAlertSourceHandler implements CommandHandler
{
    public function __construct(
        private readonly AlertSource $source,
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(PollAlertSource $command): void
    {
        foreach ($this->source->fetch() as $alert) {
            $this->commandBus->dispatch(new IngestCandidate(
                $command->tenantId,
                $alert->source,
                $alert->title,
                $alert->organizationName,
                $alert->languagePair,
                $alert->url,
                $alert->excerpt,
                $alert->externalId,
                $alert->postedAt,
                $alert->rawPayload,
            ));
        }
    }
}
