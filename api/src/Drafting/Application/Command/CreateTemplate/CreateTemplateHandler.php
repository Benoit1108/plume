<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\CreateTemplate;

use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Template\Template;
use App\Drafting\Domain\Template\TemplateId;
use App\Drafting\Domain\Template\TemplateRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

final class CreateTemplateHandler implements CommandHandler
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateTemplate $command): void
    {
        $template = Template::create(
            TemplateId::fromString($command->id),
            TenantId::fromString($command->tenantId),
            $command->name,
            DraftType::tryFrom($command->type) ?? throw InvalidValue::because(sprintf('Unknown draft type "%s".', $command->type)),
            Segment::tryFrom($command->segment) ?? throw InvalidValue::because(sprintf('Unknown segment "%s".', $command->segment)),
            LanguageCode::fromString($command->language),
            $command->subject,
            $command->body,
            $this->clock->now(),
        );

        $this->templates->save($template);
        $this->eventBus->publish(...$template->pullDomainEvents());
    }
}
