<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\MarkEmailFailed;

use App\Mailbox\Domain\Outbound\Exception\OutboundMessageNotFound;
use App\Mailbox\Domain\Outbound\OutboundMessageId;
use App\Mailbox\Domain\Outbound\OutboundMessageRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class MarkEmailFailedHandler implements CommandHandler
{
    public function __construct(
        private readonly OutboundMessageRepository $messages,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(MarkEmailFailed $command): void
    {
        $message = $this->messages->get(OutboundMessageId::fromString($command->messageId));
        // Ceinture-bretelles worker : le tenant de la commande doit être celui du message.
        if ($message->tenantId()->toString() !== $command->tenantId) {
            throw OutboundMessageNotFound::withId($command->messageId);
        }
        $message->markFailed($command->reason, $this->clock->now());
        $this->messages->save($message);
        $this->eventBus->publish(...$message->pullDomainEvents());
    }
}
