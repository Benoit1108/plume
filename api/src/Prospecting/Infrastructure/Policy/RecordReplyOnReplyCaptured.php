<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Policy;

use App\Mailbox\Domain\Outbound\Event\ReplyCaptured;
use App\Prospecting\Application\Command\RecordReply\RecordReply;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Réponse captée (Mailbox) → la piste passe en discussion, avec aperçu au
 * journal. recordReply est IDEMPOTENT : les redélivrances et les relèves
 * répétées sont sans effet. Tenant réactivé depuis l'event (worker).
 */
final class RecordReplyOnReplyCaptured
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onReplyCaptured(ReplyCaptured $event): void
    {
        $this->tenantContext->set(TenantId::fromString($event->tenantId));

        try {
            $this->commandBus->dispatch(new RecordReply($event->leadId, $event->preview));
        } catch (Conflict|NotFound $e) {
            $this->logger->info('Captured reply not applied to lead.', [
                'lead_id' => $event->leadId,
                'tenant_id' => $event->tenantId,
                'reason' => $e::class,
            ]);
        }
    }
}
