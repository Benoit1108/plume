<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\FetchAlertEmails;

use App\Mailbox\Application\AlertEmailFetcherRegistry;
use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\Exception\TokenCipherFailure;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\Event\AlertEmailReceived;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Mailbox\Domain\Mailbox\MailboxStatus;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Relève des alertes : lit le label dédié (ADR-0017 amendé) et publie un `AlertEmailReceived`
 * par email — le Sourcing décide de l'ingestion. Canal secondaire : un échec est un no-op
 * silencieux (le Scheduler repassera), sans marquer la boîte ERROR.
 */
final class FetchAlertEmailsHandler implements CommandHandler
{
    public const string LABEL = 'Plume/Alertes';

    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly AlertEmailFetcherRegistry $fetchers,
        private readonly TokenCipher $cipher,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(FetchAlertEmails $command): void
    {
        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString($command->tenantId));
        $refresh = $mailbox?->refreshToken();
        if (null === $mailbox || MailboxStatus::CONNECTED !== $mailbox->status() || null === $refresh) {
            return;
        }

        try {
            $emails = $this->fetchers->fetcherFor($mailbox->provider()->value)
                ->fetch($this->cipher->decrypt($refresh->ciphertext()), self::LABEL);
        } catch (MailSendFailed|TokenCipherFailure) {
            return;
        }

        $now = $this->clock->now();
        foreach ($emails as $email) {
            $this->eventBus->publish(new AlertEmailReceived(
                $command->tenantId,
                $email->fromAddress,
                $email->subject,
                $email->body,
                $email->externalId,
                $now,
            ));
        }
    }
}
