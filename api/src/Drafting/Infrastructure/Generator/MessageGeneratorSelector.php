<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Generator;

use App\Drafting\Application\AiBudget;
use App\Drafting\Application\AiGenerationPolicy;
use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\GeneratedMessage;
use App\Drafting\Application\MessageGenerator;

/**
 * Sélection de l'adaptateur (décision M1.4 n°4 + garde-fou coût) :
 * Claude UNIQUEMENT si ANTHROPIC_API_KEY présente ET le garde-fou de coût l'autorise (coupe-circuit
 * levé, plafond mensuel non atteint) ET la politique de session l'autorise (un tenant de démo ne
 * déclenche jamais d'appel payant). Sinon → canned (coût zéro) : le produit continue de fonctionner
 * même budget épuisé ou en démo — jamais de facture surprise, jamais d'échec pour cause de plafond.
 */
final class MessageGeneratorSelector implements MessageGenerator
{
    public function __construct(
        private readonly CannedMessageGenerator $canned,
        private readonly ClaudeMessageGenerator $claude,
        private readonly AiBudget $budget,
        private readonly AiGenerationPolicy $policy,
        private readonly string $apiKey,
    ) {
    }

    public function generate(DraftPrompt $prompt): GeneratedMessage
    {
        $paid = '' !== trim($this->apiKey)
            && $this->budget->allowsGeneration()
            && $this->policy->allowsPaidGeneration();

        return $paid ? $this->claude->generate($prompt) : $this->canned->generate($prompt);
    }
}
