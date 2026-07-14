<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\DeleteDraft;

use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class DeleteDraftHandler implements CommandHandler
{
    public function __construct(
        private readonly DraftRepository $drafts,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(DeleteDraft $command): void
    {
        $draft = $this->drafts->get(DraftId::fromString($command->draftId));
        $draft->delete($this->clock->now());
        $this->eventBus->publish(...$draft->pullDomainEvents());
        $this->drafts->remove($draft);
    }
}
