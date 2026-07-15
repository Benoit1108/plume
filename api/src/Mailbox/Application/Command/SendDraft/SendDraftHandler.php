<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\SendDraft;

use App\Mailbox\Application\DraftGateway;
use App\Mailbox\Application\RecipientResolver;
use App\Mailbox\Domain\Mailbox\Exception\MailboxNotOperational;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Mailbox\Domain\Mailbox\MailboxStatus;
use App\Mailbox\Domain\Outbound\Exception\DraftAlreadySending;
use App\Mailbox\Domain\Outbound\Exception\DraftNotSendable;
use App\Mailbox\Domain\Outbound\Exception\RecipientNotContactable;
use App\Mailbox\Domain\Outbound\OutboundMessage;
use App\Mailbox\Domain\Outbound\OutboundMessageId;
use App\Mailbox\Domain\Outbound\OutboundMessageRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;

final class SendDraftHandler implements CommandHandler
{
    public function __construct(
        private readonly OutboundMessageRepository $messages,
        private readonly MailboxRepository $mailboxes,
        private readonly DraftGateway $drafts,
        private readonly RecipientResolver $recipients,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SendDraft $command): void
    {
        // Gardes SYNCHRONES (l'utilisatrice a un retour immédiat) ; toutes
        // re-vérifiées par le worker avant l'appel provider.
        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString($command->tenantId));
        if (null === $mailbox || MailboxStatus::CONNECTED !== $mailbox->status()) {
            throw MailboxNotOperational::inStatus($mailbox?->status() ?? MailboxStatus::REVOKED);
        }

        $draft = $this->drafts->context($command->tenantId, $command->draftId)
            ?? throw InvalidValue::because(sprintf('Unknown draft "%s".', $command->draftId));
        if ('READY' !== $draft->status) {
            throw DraftNotSendable::inStatus($draft->status);
        }
        // Anti double envoi (double-clic, rejeu réseau) : un seul envoi actif par brouillon.
        if ($this->messages->existsActiveForDraft($command->tenantId, $command->draftId)) {
            throw DraftAlreadySending::forDraft($command->draftId);
        }

        $recipient = $this->recipients->resolve($command->tenantId, $draft->leadId);
        if (null === $recipient) {
            throw InvalidValue::because('This lead has no contact with an email address.');
        }
        if (!$recipient->contactAllowed) {
            throw RecipientNotContactable::create();
        }

        $message = OutboundMessage::request(
            OutboundMessageId::fromString($command->messageId),
            TenantId::fromString($command->tenantId),
            $draft->leadId,
            $command->draftId,
            $draft->type,
            EmailAddress::fromString($recipient->email),
            $this->clock->now(),
        );

        $this->messages->save($message);
        // EmailSendRequested part en asynchrone : le worker appellera le provider.
        $this->eventBus->publish(...$message->pullDomainEvents());
    }
}
