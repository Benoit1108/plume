<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\DeleteTemplate;

use App\Drafting\Domain\Template\TemplateId;
use App\Drafting\Domain\Template\TemplateRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class DeleteTemplateHandler implements CommandHandler
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(DeleteTemplate $command): void
    {
        $template = $this->templates->get(TemplateId::fromString($command->id));
        $template->delete($this->clock->now());
        $this->eventBus->publish(...$template->pullDomainEvents());
        $this->templates->remove($template);
    }
}
