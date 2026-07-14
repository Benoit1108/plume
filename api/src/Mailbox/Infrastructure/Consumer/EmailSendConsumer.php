<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Consumer;

use App\Mailbox\Application\Command\MarkEmailFailed\MarkEmailFailed;
use App\Mailbox\Application\Command\MarkEmailSent\MarkEmailSent;
use App\Mailbox\Application\DraftGateway;
use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\Exception\TokenCipherFailure;
use App\Mailbox\Application\MailSenderRegistry;
use App\Mailbox\Application\OpenThreads;
use App\Mailbox\Application\OutgoingMail;
use App\Mailbox\Application\RecipientResolver;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Mailbox\Domain\Mailbox\MailboxStatus;
use App\Mailbox\Domain\Outbound\Event\EmailSendRequested;
use App\Mailbox\Domain\Outbound\Exception\OutboundMessageNotFound;
use App\Mailbox\Domain\Outbound\Exception\OutboundMessageNotSending;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consomme EmailSendRequested (worker) : re-vérifie TOUTES les gardes
 * (RGPD prime sur la demande), déchiffre le refresh token en mémoire,
 * envoie, puis MarkEmailSent/MarkEmailFailed. Échecs = codes stables i18n ;
 * NotFound/Conflict absorbés (redélivrance = cas normal, pattern M1.4 durci).
 */
final class EmailSendConsumer
{
    public const string REASON_MAILBOX_UNAVAILABLE = 'mailbox_unavailable';
    public const string REASON_RECIPIENT_UNAVAILABLE = 'recipient_unavailable';
    public const string REASON_CONTACT_NOT_ALLOWED = 'contact_not_allowed';
    public const string REASON_SEND_FAILED = 'send_failed';

    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly DraftGateway $drafts,
        private readonly RecipientResolver $recipients,
        private readonly OpenThreads $threads,
        private readonly TokenCipher $cipher,
        private readonly MailSenderRegistry $senders,
        private readonly CommandBus $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onEmailSendRequested(EmailSendRequested $event): void
    {
        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString($event->tenantId));
        $refresh = $mailbox?->refreshToken();
        if (null === $mailbox || MailboxStatus::CONNECTED !== $mailbox->status() || null === $refresh) {
            $this->settle($event, new MarkEmailFailed($event->tenantId, $event->messageId, self::REASON_MAILBOX_UNAVAILABLE));

            return;
        }

        $draft = $this->drafts->context($event->tenantId, $event->draftId);
        $recipient = $this->recipients->resolve($event->tenantId, $event->leadId);
        if (null === $draft || null === $recipient) {
            $this->settle($event, new MarkEmailFailed($event->tenantId, $event->messageId, self::REASON_RECIPIENT_UNAVAILABLE));

            return;
        }
        if (!$recipient->contactAllowed) {
            // RGPD re-vérifiée au moment de l'envoi : elle prime sur la demande.
            $this->settle($event, new MarkEmailFailed($event->tenantId, $event->messageId, self::REASON_CONTACT_NOT_ALLOWED));

            return;
        }

        // Une relance repart DANS le fil d'origine (M2.4) ; un premier envoi en ouvre un.
        $originThread = 'FOLLOW_UP_EMAIL' === $draft->type
            ? $this->threads->latestForLead($event->tenantId, $event->leadId)
            : null;

        try {
            $threadKey = $this->senders->senderFor($mailbox->provider()->value)->send(
                $this->cipher->decrypt($refresh->ciphertext()),
                $mailbox->emailAddress()->toString(),
                new OutgoingMail($recipient->email, $recipient->name, $draft->subject, $draft->body, $originThread),
            );
        } catch (MailSendFailed|TokenCipherFailure $e) {
            $this->logger->error('Email send failed.', [
                'message_id' => $event->messageId,
                'tenant_id' => $event->tenantId,
                'exception' => $e,
            ]);
            $this->settle($event, new MarkEmailFailed($event->tenantId, $event->messageId, self::REASON_SEND_FAILED));

            return;
        }

        $this->settle($event, new MarkEmailSent($event->tenantId, $event->messageId, $threadKey));
    }

    private function settle(EmailSendRequested $event, MarkEmailSent|MarkEmailFailed $command): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (OutboundMessageNotFound|OutboundMessageNotSending $e) {
            $this->logger->info('Email send result discarded.', [
                'message_id' => $event->messageId,
                'tenant_id' => $event->tenantId,
                'reason' => $e::class,
            ]);
        }
    }
}
