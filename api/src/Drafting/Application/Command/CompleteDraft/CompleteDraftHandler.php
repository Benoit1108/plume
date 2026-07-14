<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\CompleteDraft;

use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class CompleteDraftHandler implements CommandHandler
{
    public function __construct(
        private readonly DraftRepository $drafts,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CompleteDraft $command): void
    {
        $draft = $this->drafts->get(DraftId::fromString($command->draftId));
        $draft->complete($command->subject, $command->body, $this->clock->now());
        $this->drafts->save($draft);
        $this->eventBus->publish(...$draft->pullDomainEvents());
    }
}
