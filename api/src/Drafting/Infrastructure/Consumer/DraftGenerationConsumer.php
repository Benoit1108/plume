<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Consumer;

use App\Drafting\Application\Command\CompleteDraft\CompleteDraft;
use App\Drafting\Application\Command\FailDraft\FailDraft;
use App\Drafting\Application\Exception\GenerationFailed;
use App\Drafting\Application\LeadGateway;
use App\Drafting\Application\MessageGenerator;
use App\Drafting\Domain\Draft\Event\DraftRequested;
use App\Drafting\Domain\Draft\Exception\DraftNotFound;
use App\Drafting\Domain\Draft\Exception\DraftNotGenerating;
use App\Shared\Application\Command\CommandBus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consomme DraftRequested (worker, via l'outbox) : assemble le prompt, appelle
 * le port MessageGenerator, puis CompleteDraft ou FailDraft.
 *
 * Échec → FailDraft avec un CODE de raison (i18n côté front), jamais un message
 * interne, et pas de rethrow : le retry humain passe par « Régénérer ».
 * Le détail technique part dans les logs.
 */
final class DraftGenerationConsumer
{
    public const string REASON_LEAD_UNAVAILABLE = 'lead_unavailable';
    public const string REASON_CONTACT_NOT_ALLOWED = 'contact_not_allowed';
    public const string REASON_GENERATION_FAILED = 'generation_failed';

    public function __construct(
        private readonly LeadGateway $leads,
        private readonly DraftPromptBuilder $promptBuilder,
        private readonly MessageGenerator $generator,
        private readonly CommandBus $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onDraftRequested(DraftRequested $event): void
    {
        // Re-vérification des gardes : l'état a pu changer depuis la commande
        // (piste supprimée, passage doNotContact — RGPD prime sur la demande).
        $context = $this->leads->context($event->tenantId, $event->leadId);
        if (null === $context) {
            $this->settle($event, new FailDraft($event->tenantId, $event->draftId, self::REASON_LEAD_UNAVAILABLE));

            return;
        }
        if (!$context->contactAllowed) {
            $this->settle($event, new FailDraft($event->tenantId, $event->draftId, self::REASON_CONTACT_NOT_ALLOWED));

            return;
        }

        try {
            $message = $this->generator->generate($this->promptBuilder->build($event, $context));
        } catch (GenerationFailed $e) {
            $this->logger->error('Draft generation failed.', [
                'draft_id' => $event->draftId,
                'tenant_id' => $event->tenantId,
                'exception' => $e,
            ]);
            $this->settle($event, new FailDraft($event->tenantId, $event->draftId, self::REASON_GENERATION_FAILED));

            return;
        }

        $this->settle($event, new CompleteDraft($event->tenantId, $event->draftId, $message->subject, $message->body));
    }

    /**
     * Dépose le résultat en absorbant les cas NORMAUX du monde asynchrone :
     * brouillon supprimé pendant la génération (NotFound) ou déjà réglé par une
     * livraison précédente (DraftNotGenerating, garde d'état de l'agrégat).
     * Ni retry ni queue failed pour ça — juste une trace.
     */
    private function settle(DraftRequested $event, CompleteDraft|FailDraft $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (DraftNotFound|DraftNotGenerating $e) {
            $this->logger->info('Draft generation result discarded.', [
                'draft_id' => $event->draftId,
                'tenant_id' => $event->tenantId,
                'reason' => $e::class,
            ]);
        }
    }
}
