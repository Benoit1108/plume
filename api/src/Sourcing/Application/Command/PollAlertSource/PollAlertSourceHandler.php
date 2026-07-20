<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\PollAlertSource;

use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidate;
use App\Sourcing\Application\Source\AlertSource;
use App\Sourcing\Domain\AlertFeed\AlertFeedRepository;

/**
 * Relève : chaque `ParsedAlert` devient une commande `IngestCandidate` (dispatch imbriqué,
 * même transaction). On interroge les flux ACTIFS du tenant via la source réelle ; si aucun
 * flux n'est configuré, on retombe sur la source de démonstration (dev / E2E / première prise
 * en main). Le dédoublonnage vit dans `IngestCandidate` ; le parser écarte déjà les items
 * malformés — pas de propagation d'erreur par item.
 */
final class PollAlertSourceHandler implements CommandHandler
{
    public function __construct(
        private readonly AlertFeedRepository $feeds,
        private readonly AlertSource $source,
        private readonly AlertSource $demoSource,
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(PollAlertSource $command): void
    {
        $feeds = $this->feeds->activeForTenant(TenantId::fromString($command->tenantId));

        if ([] === $feeds) {
            $this->ingest($command->tenantId, $this->demoSource->fetch(''));

            return;
        }

        foreach ($feeds as $feed) {
            $this->ingest($command->tenantId, $this->source->fetch($feed->url()));
        }
    }

    /** @param iterable<\App\Sourcing\Application\Source\ParsedAlert> $alerts */
    private function ingest(string $tenantId, iterable $alerts): void
    {
        foreach ($alerts as $alert) {
            $this->commandBus->dispatch(new IngestCandidate(
                $tenantId,
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
