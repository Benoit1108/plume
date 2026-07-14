<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\FetchReplies;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\Exception\TokenCipherFailure;
use App\Mailbox\Application\OpenThreads;
use App\Mailbox\Application\ReplyFetcherRegistry;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Mailbox\Domain\Mailbox\MailboxStatus;
use App\Mailbox\Domain\Outbound\Event\ReplyCaptured;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Relève : fils ouverts → réponses d'autrui → recordReply (IDEMPOTENT) avec
 * aperçu texte. Une boîte non opérationnelle est un no-op silencieux (le
 * Scheduler repassera) ; un échec provider marque la boîte ERROR (visible
 * et récupérable dans les Réglages).
 */
final class FetchRepliesHandler implements CommandHandler
{
    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly OpenThreads $openThreads,
        private readonly ReplyFetcherRegistry $fetchers,
        private readonly TokenCipher $cipher,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(FetchReplies $command): void
    {
        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString($command->tenantId));
        $refresh = $mailbox?->refreshToken();
        if (null === $mailbox || MailboxStatus::CONNECTED !== $mailbox->status() || null === $refresh) {
            return;
        }

        $threads = $this->openThreads->forTenant($command->tenantId);
        $now = $this->clock->now();
        if ([] === $threads) {
            $mailbox->markSyncSucceeded(null, $now);
            $this->mailboxes->save($mailbox);

            return;
        }

        try {
            $replies = $this->fetchers->fetcherFor($mailbox->provider()->value)->fetch($this->cipher->decrypt($refresh->ciphertext()), $mailbox->emailAddress()->toString(), $threads);
        } catch (MailSendFailed|TokenCipherFailure) {
            $mailbox->markSyncFailed('sync_failed', $now);
            $this->mailboxes->save($mailbox);
            $this->eventBus->publish(...$mailbox->pullDomainEvents());

            return;
        }

        foreach ($replies as $reply) {
            // Langage publié : la Prospection réagit par sa propre politique
            // (RecordReply idempotent) — jamais d'appel direct inter-contextes.
            $this->eventBus->publish(new ReplyCaptured($command->tenantId, $reply->leadId, $reply->threadKey, $reply->textPreview, $now));
        }

        $mailbox->markSyncSucceeded(null, $now);
        $this->mailboxes->save($mailbox);
    }
}
