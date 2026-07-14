<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\GenerateDraft;

use App\Drafting\Application\LeadGateway;
use App\Drafting\Domain\Draft\Draft;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftRepository;
use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Draft\Exception\DraftingNotAllowed;
use App\Drafting\Domain\Template\TemplateId;
use App\Drafting\Domain\Template\TemplateRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

final class GenerateDraftHandler implements CommandHandler
{
    public function __construct(
        private readonly DraftRepository $drafts,
        private readonly TemplateRepository $templates,
        private readonly LeadGateway $leads,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(GenerateDraft $command): void
    {
        // Frontière de contexte : la piste et sa cible sont vérifiées par le gateway.
        $context = $this->leads->context($command->tenantId, $command->leadId)
            ?? throw InvalidValue::because(sprintf('Unknown lead "%s".', $command->leadId));
        if (!$context->contactAllowed) {
            throw DraftingNotAllowed::doNotContact();
        }
        if (null !== $command->templateId) {
            $this->templates->get(TemplateId::fromString($command->templateId)); // 404 sinon
        }

        $draft = Draft::request(
            DraftId::fromString($command->id),
            TenantId::fromString($command->tenantId),
            $command->leadId,
            DraftType::tryFrom($command->type) ?? throw InvalidValue::because(sprintf('Unknown draft type "%s".', $command->type)),
            LanguageCode::fromString($command->targetLanguage),
            $command->templateId,
            $this->clock->now(),
        );

        $this->drafts->save($draft);
        // DraftRequested part en asynchrone : le worker appellera le générateur.
        $this->eventBus->publish(...$draft->pullDomainEvents());
    }
}
