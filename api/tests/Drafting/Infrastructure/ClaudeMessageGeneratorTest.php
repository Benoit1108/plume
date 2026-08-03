<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Infrastructure;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\Exception\GenerationFailed;
use App\Drafting\Infrastructure\Generator\ClaudeMessageGenerator;
use App\Tests\Support\FakeAiBudget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** ACL Anthropic : parsing du format SUBJECT/OBJET, bornes, comptage des jetons, erreurs → GenerationFailed. */
final class ClaudeMessageGeneratorTest extends TestCase
{
    private FakeAiBudget $budget;

    protected function setUp(): void
    {
        $this->budget = new FakeAiBudget();
    }

    private function generator(MockResponse $response): ClaudeMessageGenerator
    {
        return new ClaudeMessageGenerator(new MockHttpClient($response), $this->budget, 'test-key', 'claude-sonnet-5');
    }

    private function prompt(string $type = 'APPLICATION_EMAIL'): DraftPrompt
    {
        return new DraftPrompt($type, 'fr', 'en>fr', 'TO_CONTACT', 'Éditions du Nord', 'PUBLISHING', null, null, null, null, null, null);
    }

    /** @param array<string, mixed> $payload */
    private static function apiResponse(array $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    public function testParsesSubjectAndBody(): void
    {
        $response = self::apiResponse(['content' => [['type' => 'text', 'text' => "SUBJECT: Candidature — traduction\n\nBonjour,\nvoici mon message."]]]);

        $message = $this->generator($response)->generate($this->prompt());

        self::assertSame('Candidature — traduction', $message->subject);
        self::assertSame("Bonjour,\nvoici mon message.", $message->body);
    }

    public function testRecordsTokenUsageAgainstTheBudget(): void
    {
        $response = self::apiResponse([
            'content' => [['type' => 'text', 'text' => "SUBJECT: X\n\nCorps."]],
            'usage' => ['input_tokens' => 320, 'output_tokens' => 210],
        ]);

        $this->generator($response)->generate($this->prompt());

        self::assertSame([[320, 210]], $this->budget->recorded);
    }

    public function testMissingUsageDoesNotRecord(): void
    {
        $response = self::apiResponse(['content' => [['type' => 'text', 'text' => "SUBJECT: X\n\nCorps."]]]);

        $this->generator($response)->generate($this->prompt());

        self::assertSame([], $this->budget->recorded);
    }

    public function testParsesFrenchObjetMarkerToo(): void
    {
        $response = self::apiResponse(['content' => [['type' => 'text', 'text' => "OBJET : Ma candidature\n\nCorps."]]]);

        $message = $this->generator($response)->generate($this->prompt());

        self::assertSame('Ma candidature', $message->subject);
        self::assertSame('Corps.', $message->body);
    }

    public function testTextWithoutSubjectMarkerBecomesBodyOnly(): void
    {
        $response = self::apiResponse(['content' => [['type' => 'text', 'text' => 'Madame, Monsieur, ma lettre.']]]);

        $message = $this->generator($response)->generate($this->prompt('COVER_LETTER'));

        self::assertNull($message->subject);
        self::assertSame('Madame, Monsieur, ma lettre.', $message->body);
    }

    public function testOverlongSubjectIsTruncatedToColumnBound(): void
    {
        $longSubject = str_repeat('à', 300);
        $response = self::apiResponse(['content' => [['type' => 'text', 'text' => "SUBJECT: {$longSubject}\n\nCorps."]]]);

        $message = $this->generator($response)->generate($this->prompt());

        // Colonne subject VARCHAR(255) : jamais d'échec de persistance en aval.
        self::assertSame(255, mb_strlen((string) $message->subject));
    }

    public function testEmptyContentIsAGenerationFailure(): void
    {
        $response = self::apiResponse(['content' => [['type' => 'text', 'text' => '   ']]]);

        $this->expectException(GenerationFailed::class);
        $this->generator($response)->generate($this->prompt());
    }

    public function testHttpErrorIsAGenerationFailure(): void
    {
        $response = new MockResponse('{"error":{"type":"overloaded_error"}}', ['http_code' => 529]);

        $this->expectException(GenerationFailed::class);
        $this->generator($response)->generate($this->prompt());
    }
}
