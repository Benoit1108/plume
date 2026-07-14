<?php

declare(strict_types=1);

namespace App\Drafting\Application;

use App\Drafting\Application\Exception\GenerationFailed;

/**
 * Port de génération de messages. Le domaine ignore « Claude » : l'adaptateur
 * réel (API Anthropic) ou local (canned) est choisi en Infrastructure par l'env.
 */
interface MessageGenerator
{
    /** @throws GenerationFailed */
    public function generate(DraftPrompt $prompt): GeneratedMessage;
}
