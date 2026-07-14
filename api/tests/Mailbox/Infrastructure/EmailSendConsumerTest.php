<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Command\MarkEmailFailed\MarkEmailFailed;
use App\Mailbox\Application\Command\MarkEmailSent\MarkEmailSent;
use App\Mailbox\Application\DraftContext;
use App\Mailbox\Application\DraftGateway;
use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\MailSender;
use App\Mailbox\Application\OpenThreads;
use App\Mailbox\Application\OutgoingMail;
use App\Mailbox\Application\Recipient;
use App\Mailbox\Application\RecipientResolver;
use App\Mailbox\Domain\Mailbox\ConnectedMailbox;
use App\Mailbox\Domain\Mailbox\EncryptedToken;
use App\Mailbox\Domain\Mailbox\MailboxId;
use App\Mailbox\Domain\Mailbox\MailProviderName;
use App\Mailbox\Domain\Outbound\Event\EmailSendRequested;
use App\Mailbox\Infrastructure\Consumer\EmailSendConsumer;
use App\Shared\Application\Command\Command;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FakeTokenCipher;
use App\Tests\Support\HandlerMapCommandBus;
use App\Tests\Support\InMemoryMailboxRepository;
use App\Tests\Support\SingleMailSenderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** L'orchestrateur d'envoi : gardes re-vérifiées, codes stables, déchiffrement en mémoire. */
final class EmailSendConsumerTest extends TestCase
{
    private const string TENANT = '0197b7e2-0000-7000-8000-000000000001';

    /** @var Command[] */
    private array $dispatched = [];
    private InMemoryMailboxRepository $mailboxes;
    private DraftContext $draft;
    private Recipient $recipient;

    protected function setUp(): void
    {
        $this->dispatched = [];
        $this->mailboxes = new InMemoryMailboxRepository();
        $this->draft = new DraftContext('lead-1', 'APPLICATION_EMAIL', 'Objet', 'Corps relu.', 'READY');
        $this->recipient = new Recipient('jeanne@editions.example', 'Jeanne', true);
    }

    private function connectMailbox(): void
    {
        $cipher = new FakeTokenCipher();
        $this->mailboxes->save(ConnectedMailbox::connect(
            MailboxId::fromString('mb-1'),
            TenantId::fromString(self::TENANT),
            MailProviderName::GMAIL,
            EmailAddress::fromString('marie@gmail.example'),
            EncryptedToken::fromCiphertext($cipher->encrypt('access-plain')),
            EncryptedToken::fromCiphertext($cipher->encrypt('refresh-plain')),
            new \DateTimeImmutable('2026-07-14 16:00:00'),
        ));
    }

    private function consumer(MailSender $sender): EmailSendConsumer
    {
        $record = function (Command $command): void {
            $this->dispatched[] = $command;
        };
        $drafts = new class($this->draft) implements DraftGateway {
            public function __construct(private readonly ?DraftContext $draft)
            {
            }

            public function context(string $tenantId, string $draftId): ?DraftContext
            {
                return $this->draft;
            }
        };
        $recipients = new class($this->recipient) implements RecipientResolver {
            public function __construct(private readonly ?Recipient $recipient)
            {
            }

            public function resolve(string $tenantId, string $leadId): ?Recipient
            {
                return $this->recipient;
            }
        };

        $threads = new class implements OpenThreads {
            public function forTenant(string $tenantId): array
            {
                return [];
            }

            public function latestForLead(string $tenantId, string $leadId): ?string
            {
                return 'lead-1' === $leadId ? 'thread-origine' : null;
            }
        };

        return new EmailSendConsumer(
            $this->mailboxes,
            $drafts,
            $recipients,
            $threads,
            new FakeTokenCipher(),
            new SingleMailSenderRegistry($sender),
            new HandlerMapCommandBus([MarkEmailSent::class => $record, MarkEmailFailed::class => $record]),
            new NullLogger(),
        );
    }

    /** @param callable(string, string, OutgoingMail): string $sendImpl */
    private static function sender(callable $sendImpl): MailSender
    {
        return new class($sendImpl) implements MailSender {
            /** @param callable(string, string, OutgoingMail): string $impl */
            public function __construct(private $impl)
            {
            }

            public function send(string $refreshTokenPlain, string $fromEmail, OutgoingMail $mail): string
            {
                return ($this->impl)($refreshTokenPlain, $fromEmail, $mail);
            }
        };
    }

    private function event(): EmailSendRequested
    {
        return new EmailSendRequested(self::TENANT, 'out-1', 'draft-1', 'lead-1', new \DateTimeImmutable('2026-07-14 17:00:00'));
    }

    public function testSendsWithDecryptedRefreshTokenAndMarksSent(): void
    {
        $this->connectMailbox();
        $captured = [];
        $consumer = $this->consumer(self::sender(function (string $refresh, string $from, OutgoingMail $mail) use (&$captured): string {
            $captured = [$refresh, $from, $mail->toEmail, $mail->subject];

            return 'thread-9';
        }));

        $consumer->onEmailSendRequested($this->event());

        // Le refresh token arrive DÉCHIFFRÉ au sender (jamais le ciphertext).
        self::assertSame(['refresh-plain', 'marie@gmail.example', 'jeanne@editions.example', 'Objet'], $captured);
        $command = $this->dispatched[0];
        self::assertInstanceOf(MarkEmailSent::class, $command);
        self::assertSame('thread-9', $command->threadKey);
        self::assertSame(self::TENANT, $command->tenantId);
    }

    public function testFollowUpIsSentInsideTheOriginThread(): void
    {
        $this->connectMailbox();
        $this->draft = new DraftContext('lead-1', 'FOLLOW_UP_EMAIL', 'Re : candidature', 'Je relance.', 'READY');
        $captured = null;
        $consumer = $this->consumer(self::sender(function (string $r, string $f, OutgoingMail $mail) use (&$captured): string {
            $captured = $mail->threadKey;

            return $mail->threadKey ?? 'nouveau-fil';
        }));

        $consumer->onEmailSendRequested($this->event());

        self::assertSame('thread-origine', $captured); // M2.4 : la relance vit DANS le fil
    }

    public function testNoOperationalMailboxFailsWithStableCode(): void
    {
        $consumer = $this->consumer(self::sender(fn (): string => 'unused'));

        $consumer->onEmailSendRequested($this->event());

        $command = $this->dispatched[0];
        self::assertInstanceOf(MarkEmailFailed::class, $command);
        self::assertSame(EmailSendConsumer::REASON_MAILBOX_UNAVAILABLE, $command->reason);
    }

    public function testRgpdFlippedAfterRequestBlocksTheSend(): void
    {
        $this->connectMailbox();
        $this->recipient = new Recipient('jeanne@editions.example', 'Jeanne', false);
        $sent = false;
        $consumer = $this->consumer(self::sender(function () use (&$sent): string {
            $sent = true;

            return 'thread';
        }));

        $consumer->onEmailSendRequested($this->event());

        self::assertFalse($sent, 'RGPD prime : le provider ne doit JAMAIS être appelé.');
        $command = $this->dispatched[0];
        self::assertInstanceOf(MarkEmailFailed::class, $command);
        self::assertSame(EmailSendConsumer::REASON_CONTACT_NOT_ALLOWED, $command->reason);
    }

    public function testProviderFailureIsAStableCode(): void
    {
        $this->connectMailbox();
        $consumer = $this->consumer(self::sender(function (): string {
            throw MailSendFailed::because('quota');
        }));

        $consumer->onEmailSendRequested($this->event());

        $command = $this->dispatched[0];
        self::assertInstanceOf(MarkEmailFailed::class, $command);
        self::assertSame(EmailSendConsumer::REASON_SEND_FAILED, $command->reason);
    }
}
