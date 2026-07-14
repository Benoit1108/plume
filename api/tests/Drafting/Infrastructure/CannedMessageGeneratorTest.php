<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Infrastructure;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Infrastructure\Generator\CannedMessageGenerator;
use PHPUnit\Framework\TestCase;

final class CannedMessageGeneratorTest extends TestCase
{
    private CannedMessageGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new CannedMessageGenerator();
    }

    public function testInterpolatesTemplateVariables(): void
    {
        $message = $this->generator->generate($this->prompt(
            templateSubject: 'Candidature — traduction {{langues}}',
            templateBody: "Bonjour {{contact}},\n\nJe contacte {{organisation}}.\n\n{{bio}}\n\n{{signature}}",
        ));

        self::assertSame('Candidature — traduction EN → FR', $message->subject);
        self::assertStringContainsString('Bonjour Jeanne Duval,', $message->body);
        self::assertStringContainsString('Je contacte Éditions du Nord.', $message->body);
        self::assertStringContainsString('Traductrice EN>FR depuis 2020.', $message->body);
        self::assertStringContainsString('Marie', $message->body);
    }

    public function testFallsBackToSkeletonPerTypeAndLanguage(): void
    {
        $french = $this->generator->generate($this->prompt(type: 'APPLICATION_EMAIL', targetLanguage: 'fr'));
        self::assertNotNull($french->subject);
        self::assertStringContainsString('Éditions du Nord', $french->body);

        $english = $this->generator->generate($this->prompt(type: 'FOLLOW_UP_EMAIL', targetLanguage: 'en'));
        self::assertStringContainsString('following up', $english->body);

        $letter = $this->generator->generate($this->prompt(type: 'COVER_LETTER', targetLanguage: 'fr'));
        self::assertNull($letter->subject);
    }

    public function testMissingProfileLeavesNoHoles(): void
    {
        $message = $this->generator->generate($this->prompt(bio: null, signature: null, contactName: null));

        self::assertStringNotContainsString('{{', $message->body);
        self::assertStringNotContainsString("\n\n\n", $message->body);
        self::assertStringContainsString('Madame, Monsieur', $message->body);
    }

    private function prompt(
        string $type = 'APPLICATION_EMAIL',
        string $targetLanguage = 'fr',
        ?string $contactName = 'Jeanne Duval',
        ?string $bio = 'Traductrice EN>FR depuis 2020.',
        ?string $signature = 'Marie',
        ?string $templateSubject = null,
        ?string $templateBody = null,
    ): DraftPrompt {
        return new DraftPrompt(
            type: $type,
            targetLanguage: $targetLanguage,
            languagePair: 'en>fr',
            leadStatus: 'TO_CONTACT',
            organizationName: 'Éditions du Nord',
            segment: 'PUBLISHING',
            contactName: $contactName,
            bio: $bio,
            specialties: null,
            signature: $signature,
            templateSubject: $templateSubject,
            templateBody: $templateBody,
        );
    }
}
