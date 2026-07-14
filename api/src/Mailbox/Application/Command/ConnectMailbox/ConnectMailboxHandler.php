<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Command\ConnectMailbox;

use App\Mailbox\Application\Exception\MailboxConnectionFailed;
use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\ConnectedMailbox;
use App\Mailbox\Domain\Mailbox\EncryptedToken;
use App\Mailbox\Domain\Mailbox\MailboxId;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Mailbox\Domain\Mailbox\MailProviderName;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;

final class ConnectMailboxHandler implements CommandHandler
{
    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly MailboxConnector $connector,
        private readonly TokenCipher $cipher,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ConnectMailbox $command): void
    {
        $provider = MailProviderName::tryFrom($command->provider)
            ?? throw InvalidValue::because(sprintf('Unknown mail provider "%s".', $command->provider));
        $tenantId = TenantId::fromString($command->tenantId);
        $now = $this->clock->now();

        // Le clair ne vit que sur cette pile : chiffré avant toute persistance.
        try {
            $tokens = $this->connector->exchangeCode($command->code);
        } catch (MailboxConnectionFailed $e) {
            // Code expiré/refusé/panne : erreur MÉTIER propre (422), pas une 500.
            throw InvalidValue::because('OAuth connection failed — please retry.');
        }
        $email = EmailAddress::fromString($tokens->emailAddress);
        $access = EncryptedToken::fromCiphertext($this->cipher->encrypt($tokens->accessToken));
        $refresh = EncryptedToken::fromCiphertext($this->cipher->encrypt($tokens->refreshToken));

        // Une boîte par tenant (V1) : reconnexion si elle existe, création sinon.
        $mailbox = $this->mailboxes->findForTenant($tenantId);
        if (null !== $mailbox) {
            $mailbox->reconnect($email, $access, $refresh, $now);
        } else {
            $mailbox = ConnectedMailbox::connect(
                MailboxId::fromString($command->mailboxId),
                $tenantId,
                $provider,
                $email,
                $access,
                $refresh,
                $now,
            );
        }

        $this->mailboxes->save($mailbox);
        $this->eventBus->publish(...$mailbox->pullDomainEvents());
    }
}
