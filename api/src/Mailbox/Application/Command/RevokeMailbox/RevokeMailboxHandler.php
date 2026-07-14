<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\RevokeMailbox;

use App\Mailbox\Application\Exception\TokenCipherFailure;
use App\Mailbox\Application\MailboxConnectorRegistry;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\Exception\MailboxNotFound;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\ValueObject\TenantId;

final class RevokeMailboxHandler implements CommandHandler
{
    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly MailboxConnectorRegistry $connectors,
        private readonly TokenCipher $cipher,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RevokeMailbox $command): void
    {
        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString($command->tenantId))
            ?? throw MailboxNotFound::forTenant();

        // Révocation côté provider d'abord (best effort : un token déjà mort
        // ou indéchiffrable ne doit pas empêcher la déconnexion côté app).
        $refresh = $mailbox->refreshToken();
        if (null !== $refresh) {
            try {
                $this->connectors->connectorFor($mailbox->provider()->value)->revoke($this->cipher->decrypt($refresh->ciphertext()));
            } catch (TokenCipherFailure) {
                // Clé changée/données corrompues : on efface quand même côté app.
            }
        }

        $mailbox->revoke($this->clock->now());
        $this->mailboxes->save($mailbox);
        $this->eventBus->publish(...$mailbox->pullDomainEvents());
    }
}
