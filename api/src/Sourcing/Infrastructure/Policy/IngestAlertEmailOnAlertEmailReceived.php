<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Policy;

use App\Mailbox\Domain\Mailbox\Event\AlertEmailReceived;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use App\Sourcing\Application\AlertEmail\AlertEmailParser;
use App\Sourcing\Application\Command\IngestCandidate\IngestCandidate;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Email d'alerte capté (Mailbox) → parsé et ingéré dans la file de tri (Sourcing).
 * Le dédoublonnage (`externalId` = id de message) rend les relèves répétées sans effet.
 * Tenant réactivé depuis l'event (worker). Jamais d'appel direct inter-contextes.
 */
final class IngestAlertEmailOnAlertEmailReceived
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly AlertEmailParser $parser,
        private readonly TenantScope $tenantScope,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onAlertEmailReceived(AlertEmailReceived $event): void
    {
        $this->tenantScope->activate(TenantId::fromString($event->tenantId));

        foreach ($this->parser->parse($event->fromAddress, $event->subject, $event->body, $event->externalId) as $alert) {
            try {
                $this->commandBus->dispatch(new IngestCandidate(
                    $event->tenantId,
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
            } catch (DomainError $e) {
                $this->logger->info('Alert email not ingested.', [
                    'tenant_id' => $event->tenantId,
                    'reason' => $e::class,
                ]);
            }
        }
    }
}
