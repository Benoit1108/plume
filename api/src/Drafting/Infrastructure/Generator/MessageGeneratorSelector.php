<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Generator;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\GeneratedMessage;
use App\Drafting\Application\MessageGenerator;

/**
 * Sélection de l'adaptateur par l'env (décision M1.4 n°4) :
 * ANTHROPIC_API_KEY présente → Claude, absente → canned (coût zéro).
 */
final class MessageGeneratorSelector implements MessageGenerator
{
    public function __construct(
        private readonly CannedMessageGenerator $canned,
        private readonly ClaudeMessageGenerator $claude,
        private readonly string $apiKey,
    ) {
    }

    public function generate(DraftPrompt $prompt): GeneratedMessage
    {
        return '' === trim($this->apiKey)
            ? $this->canned->generate($prompt)
            : $this->claude->generate($prompt);
    }
}
