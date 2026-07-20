<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Drafting\Domain\Draft\Event\DraftGenerated;
use App\Mailbox\Domain\Mailbox\Event\AlertEmailReceived;
use App\Mailbox\Domain\Outbound\Event\EmailSendFailed;
use App\Mailbox\Domain\Outbound\Event\EmailSent;
use App\Mailbox\Domain\Outbound\Event\ReplyCaptured;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contrat des events « langage publié » consommés PAR UN AUTRE CONTEXTE (policies/projecteurs
 * en Infrastructure). deptrac-contexts ne couvre que Domain+Application ; ce test verrouille la
 * forme de ces events pour qu'un renommage dans le contexte propriétaire soit un choix conscient
 * (le test casse) et non une rupture silencieuse d'un consommateur cross-contexte.
 *
 * Consommateurs : Prospecting\Infrastructure\Policy\{AdvanceLeadOnEmailSent,RecordReplyOnReplyCaptured},
 * Prospecting\Infrastructure\Projection\InteractionProjector, Sourcing\...\IngestAlertEmailOnAlertEmailReceived.
 */
final class PublishedEventContractTest extends TestCase
{
    /** @return iterable<string, array{class-string, list<string>}> */
    public static function publishedEvents(): iterable
    {
        yield 'ReplyCaptured' => [ReplyCaptured::class, ['tenantId', 'leadId', 'threadKey', 'preview']];
        yield 'EmailSent' => [EmailSent::class, ['tenantId', 'messageId', 'leadId', 'draftType', 'threadKey']];
        yield 'EmailSendFailed' => [EmailSendFailed::class, ['tenantId', 'messageId', 'leadId', 'reason']];
        yield 'DraftGenerated' => [DraftGenerated::class, ['tenantId', 'draftId', 'leadId', 'type']];
        yield 'AlertEmailReceived' => [AlertEmailReceived::class, ['tenantId', 'fromAddress', 'subject', 'body', 'externalId']];
    }

    /**
     * @param class-string $class
     * @param list<string> $expected
     */
    #[DataProvider('publishedEvents')]
    public function testPublishedEventKeepsItsContract(string $class, array $expected): void
    {
        self::assertTrue(class_exists($class), sprintf('Event publié introuvable : %s', $class));

        $actual = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC),
        );
        sort($actual);
        sort($expected);

        self::assertSame($expected, $actual, sprintf(
            'Contrat de %s modifié — un consommateur cross-contexte en dépend (mettre à jour de concert).',
            $class,
        ));
    }
}
