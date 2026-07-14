<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Policy;

use App\Mailbox\Domain\Outbound\Event\EmailSent;
use App\Prospecting\Application\Command\ContactLead\ContactLead;
use App\Prospecting\Application\Command\RecordFollowUp\RecordFollowUp;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Décision D3 (M2) : l'envoi fait avancer la piste — une relance envoyée
 * compte « relance faite », tout autre envoi compte « contact établi ».
 * Transition déjà faite ou interdite → no-op tracé : la politique est
 * idempotente face aux redélivrances, l'email reste au journal quoi qu'il en soit.
 */
final class AdvanceLeadOnEmailSent
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onEmailSent(EmailSent $event): void
    {
        // Worker : pas de contexte de requête — le tenant vient de l'EVENT et est
        // réactivé explicitement (pattern acté dès M1.2) pour que les gardes
        // fail-closed (gateway RGPD) travaillent dans le bon périmètre.
        $this->tenantContext->set(TenantId::fromString($event->tenantId));

        $command = 'FOLLOW_UP_EMAIL' === $event->draftType
            ? new RecordFollowUp($event->leadId)
            : new ContactLead($event->leadId);

        try {
            $this->commandBus->dispatch($command);
        } catch (Conflict|NotFound $e) {
            $this->logger->info('Lead not advanced on email sent.', [
                'lead_id' => $event->leadId,
                'tenant_id' => $event->tenantId,
                'reason' => $e::class,
            ]);
        }
    }
}
