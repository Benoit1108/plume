<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Generator;

use App\Drafting\Application\AiBudget;
use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\GeneratedMessage;
use App\Drafting\Application\MessageGenerator;

/**
 * Sélection de l'adaptateur (décision M1.4 n°4 + garde-fou coût) :
 * Claude UNIQUEMENT si ANTHROPIC_API_KEY présente ET le garde-fou l'autorise (coupe-circuit levé et
 * plafond mensuel non atteint). Sinon → canned (coût zéro) : le produit continue de fonctionner même
 * budget épuisé — jamais de facture surprise, jamais d'échec de génération pour cause de plafond.
 */
final class MessageGeneratorSelector implements MessageGenerator
{
    public function __construct(
        private readonly CannedMessageGenerator $canned,
        private readonly ClaudeMessageGenerator $claude,
        private readonly AiBudget $budget,
        private readonly string $apiKey,
    ) {
    }

    public function generate(DraftPrompt $prompt): GeneratedMessage
    {
        return '' === trim($this->apiKey) || !$this->budget->allowsGeneration()
            ? $this->canned->generate($prompt)
            : $this->claude->generate($prompt);
    }
}
