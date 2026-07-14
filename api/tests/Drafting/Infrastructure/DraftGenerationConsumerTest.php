<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Infrastructure;

use App\Drafting\Application\Command\CompleteDraft\CompleteDraft;
use App\Drafting\Application\Command\FailDraft\FailDraft;
use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\Exception\GenerationFailed;
use App\Drafting\Application\GeneratedMessage;
use App\Drafting\Application\LeadContext;
use App\Drafting\Application\MessageGenerator;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftStatus;
use App\Drafting\Domain\Draft\Event\DraftRequested;
use App\Drafting\Domain\Draft\Exception\DraftNotFound;
use App\Drafting\Domain\Draft\Exception\DraftNotGenerating;
use App\Drafting\Infrastructure\Consumer\DraftGenerationConsumer;
use App\Drafting\Infrastructure\Consumer\DraftPromptBuilder;
use App\Shared\Application\Command\Command;
use App\Tests\Support\FakeLeadGateway;
use App\Tests\Support\HandlerMapCommandBus;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * L'orchestrateur du flux asynchrone M1.4 : gardes re-vérifiées, codes d'échec
 * stables, dégradation gabarit, absorption des cas normaux (NotFound/Conflict).
 */
final class DraftGenerationConsumerTest extends TestCase
{
    private const string TENANT = '0197b7e2-0000-7000-8000-000000000001';

    /** @var Command[] */
    private array $dispatched = [];
    private FakeLeadGateway $leads;
    private ?\Throwable $handlerThrows = null;

    protected function setUp(): void
    {
        $this->dispatched = [];
        $this->leads = new FakeLeadGateway();
        $this->handlerThrows = null;
    }

    private function consumer(MessageGenerator $generator): DraftGenerationConsumer
    {
        $record = function (Command $command): void {
            if (null !== $this->handlerThrows) {
                throw $this->handlerThrows;
            }
            $this->dispatched[] = $command;
        };
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        return new DraftGenerationConsumer(
            $this->leads,
            new DraftPromptBuilder($connection),
            $generator,
            new HandlerMapCommandBus([CompleteDraft::class => $record, FailDraft::class => $record]),
            new NullLogger(),
        );
    }

    private function event(): DraftRequested
    {
        return new DraftRequested(self::TENANT, 'draft-1', 'lead-1', 'APPLICATION_EMAIL', 'fr', null, new \DateTimeImmutable('2026-07-14 10:00:00'));
    }

    private function context(bool $contactAllowed = true): LeadContext
    {
        return new LeadContext('org-1', 'Éditions du Nord', 'PUBLISHING', 'en>fr', 'TO_CONTACT', 'Jeanne Duval', $contactAllowed);
    }

    private static function staticGenerator(?string $subject, string $body): MessageGenerator
    {
        return new class($subject, $body) implements MessageGenerator {
            public function __construct(private readonly ?string $subject, private readonly string $body)
            {
            }

            public function generate(DraftPrompt $prompt): GeneratedMessage
            {
                return new GeneratedMessage($this->subject, $this->body);
            }
        };
    }

    private static function failingGenerator(): MessageGenerator
    {
        return new class implements MessageGenerator {
            public function generate(DraftPrompt $prompt): GeneratedMessage
            {
                throw GenerationFailed::because('boom');
            }
        };
    }

    public function testCompletesDraftWithGeneratedMessageAndTenant(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context());

        $this->consumer(self::staticGenerator('Objet', 'Corps généré.'))->onDraftRequested($this->event());

        self::assertCount(1, $this->dispatched);
        $command = $this->dispatched[0];
        self::assertInstanceOf(CompleteDraft::class, $command);
        self::assertSame(self::TENANT, $command->tenantId);
        self::assertSame('draft-1', $command->draftId);
        self::assertSame('Objet', $command->subject);
        self::assertSame('Corps généré.', $command->body);
    }

    public function testFailsWithLeadUnavailableWhenLeadIsGone(): void
    {
        $this->consumer(self::staticGenerator(null, 'x'))->onDraftRequested($this->event());

        $command = $this->dispatched[0];
        self::assertInstanceOf(FailDraft::class, $command);
        self::assertSame(DraftGenerationConsumer::REASON_LEAD_UNAVAILABLE, $command->reason);
        self::assertSame(self::TENANT, $command->tenantId);
    }

    public function testFailsWithContactNotAllowedWhenRgpdFlippedAfterRequest(): void
    {
        // RGPD prime sur la demande : doNotContact activé APRÈS la commande.
        $this->leads->add(self::TENANT, 'lead-1', $this->context(contactAllowed: false));

        $this->consumer(self::staticGenerator(null, 'x'))->onDraftRequested($this->event());

        $command = $this->dispatched[0];
        self::assertInstanceOf(FailDraft::class, $command);
        self::assertSame(DraftGenerationConsumer::REASON_CONTACT_NOT_ALLOWED, $command->reason);
    }

    public function testFailsWithStableCodeWhenGeneratorBreaks(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context());

        $this->consumer(self::failingGenerator())->onDraftRequested($this->event());

        $command = $this->dispatched[0];
        self::assertInstanceOf(FailDraft::class, $command);
        // Code stable (i18n front), jamais le message interne « boom ».
        self::assertSame(DraftGenerationConsumer::REASON_GENERATION_FAILED, $command->reason);
    }

    public function testDraftDeletedDuringGenerationIsAbsorbed(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context());
        $this->handlerThrows = DraftNotFound::withId(DraftId::fromString('draft-1'));

        $this->consumer(self::staticGenerator(null, 'x'))->onDraftRequested($this->event());

        self::assertSame([], $this->dispatched); // ni retry, ni exception : cas normal absorbé
    }

    public function testRedeliveryAgainstSettledDraftIsAbsorbed(): void
    {
        $this->leads->add(self::TENANT, 'lead-1', $this->context());
        $this->handlerThrows = DraftNotGenerating::inStatus(DraftStatus::READY);

        $this->consumer(self::staticGenerator(null, 'x'))->onDraftRequested($this->event());

        self::assertSame([], $this->dispatched);
    }
}
