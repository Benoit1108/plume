<?php

declare(strict_types=1);

namespace App\Tests\Drafting\Infrastructure;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Infrastructure\Generator\CannedMessageGenerator;
use App\Drafting\Infrastructure\Generator\ClaudeMessageGenerator;
use App\Drafting\Infrastructure\Generator\MessageGeneratorSelector;
use App\Tests\Support\FakeAiBudget;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Sélection Claude/canned : clé absente OU garde-fou fermé (coupe-circuit/plafond) → canned gratuit,
 * SANS appeler l'API payante. Le produit continue de fonctionner budget épuisé — jamais de facture
 * surprise ni d'échec de génération.
 */
final class MessageGeneratorSelectorTest extends TestCase
{
    private function prompt(): DraftPrompt
    {
        return new DraftPrompt('APPLICATION_EMAIL', 'fr', 'en>fr', 'TO_CONTACT', 'Éditions du Nord', 'PUBLISHING', null, null, null, null, null, null);
    }

    /** MockHttpClient qui COMPTE les appels (0 attendu quand on doit rester sur le canned). */
    private function claudeSpy(int &$calls): ClaudeMessageGenerator
    {
        $http = new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse(
                json_encode(['content' => [['type' => 'text', 'text' => 'CLAUDE_OUTPUT']], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]], \JSON_THROW_ON_ERROR),
                ['response_headers' => ['content-type' => 'application/json']],
            );
        });

        return new ClaudeMessageGenerator($http, new FakeAiBudget(), 'test-key', 'claude-sonnet-5');
    }

    public function testUsesClaudeWhenKeyPresentAndBudgetAllows(): void
    {
        $calls = 0;
        $selector = new MessageGeneratorSelector(new CannedMessageGenerator(), $this->claudeSpy($calls), new FakeAiBudget(allows: true), 'test-key');

        $message = $selector->generate($this->prompt());

        self::assertSame(1, $calls);
        self::assertStringContainsString('CLAUDE_OUTPUT', $message->body);
    }

    public function testFallsBackToCannedWhenBudgetDisallows(): void
    {
        $calls = 0;
        $selector = new MessageGeneratorSelector(new CannedMessageGenerator(), $this->claudeSpy($calls), new FakeAiBudget(allows: false), 'test-key');

        $message = $selector->generate($this->prompt());

        self::assertSame(0, $calls); // API payante JAMAIS appelée
        self::assertStringNotContainsString('CLAUDE_OUTPUT', $message->body);
        self::assertNotSame('', $message->body); // le canned produit bien un message
    }

    public function testFallsBackToCannedWhenKeyAbsent(): void
    {
        $calls = 0;
        $selector = new MessageGeneratorSelector(new CannedMessageGenerator(), $this->claudeSpy($calls), new FakeAiBudget(allows: true), '');

        $selector->generate($this->prompt());

        self::assertSame(0, $calls);
    }
}
