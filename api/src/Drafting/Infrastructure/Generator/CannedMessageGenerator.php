<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Generator;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\GeneratedMessage;
use App\Drafting\Application\MessageGenerator;

/**
 * Générateur local déterministe — utilisé sans clé API (dev, tests, CI, E2E),
 * coût zéro. Interpole le gabarit fourni, sinon un squelette par type/langue.
 */
final class CannedMessageGenerator implements MessageGenerator
{
    public function generate(DraftPrompt $prompt): GeneratedMessage
    {
        [$subject, $body] = null !== $prompt->templateBody
            ? [$prompt->templateSubject, $prompt->templateBody]
            : $this->skeleton($prompt);

        return new GeneratedMessage(
            null === $subject ? null : $this->clean($this->interpolate($subject, $prompt)),
            $this->clean($this->interpolate($body, $prompt)),
        );
    }

    private function interpolate(string $text, DraftPrompt $prompt): string
    {
        $isFrench = 'fr' === $prompt->targetLanguage;
        $variables = [
            '{{contact}}' => $prompt->contactName ?? ($isFrench ? 'Madame, Monsieur' : 'Madam or Sir'),
            '{{organisation}}' => $prompt->organizationName,
            '{{langues}}' => $this->languages($prompt->languagePair),
            '{{bio}}' => $prompt->bio ?? '',
            '{{specialites}}' => $prompt->specialties ?? '',
            '{{signature}}' => $prompt->signature ?? '',
        ];

        return strtr($text, $variables);
    }

    /** @return array{0: ?string, 1: string} */
    private function skeleton(DraftPrompt $prompt): array
    {
        $isFrench = 'fr' === $prompt->targetLanguage;

        return match ($prompt->type) {
            'COVER_LETTER' => $isFrench
                ? [null, "Madame, Monsieur,\n\nTraductrice indépendante ({{langues}}), je souhaite proposer mes services à {{organisation}}.\n\n{{bio}}\n\n{{specialites}}\n\nJe me tiens à votre disposition pour un essai ou un entretien.\n\n{{signature}}"]
                : [null, "Dear {{contact}},\n\nAs a freelance translator ({{langues}}), I would like to offer my services to {{organisation}}.\n\n{{bio}}\n\n{{specialites}}\n\nI remain available for a translation test or an interview.\n\n{{signature}}"],
            'FOLLOW_UP_EMAIL' => $isFrench
                ? ['Re : proposition de collaboration — {{langues}}', "Bonjour {{contact}},\n\nJe me permets de revenir vers vous au sujet de mon message adressé à {{organisation}}.\n\nJe reste disponible pour un essai ou un échange.\n\n{{signature}}"]
                : ['Re: freelance collaboration — {{langues}}', "Hello {{contact}},\n\nI am following up on my previous message to {{organisation}}.\n\nI remain available for a test or a quick call.\n\n{{signature}}"],
            default => $isFrench
                ? ['Proposition de collaboration — traduction {{langues}}', "Bonjour {{contact}},\n\nTraductrice indépendante ({{langues}}), je me permets de contacter {{organisation}} pour proposer mes services.\n\n{{bio}}\n\nJe serais ravie d'échanger sur vos besoins.\n\n{{signature}}"]
                : ['Freelance translation — {{langues}}', "Hello {{contact}},\n\nI am a freelance translator ({{langues}}) reaching out to {{organisation}} to offer my services.\n\n{{bio}}\n\nI would be happy to discuss your needs.\n\n{{signature}}"],
        };
    }

    /** `en>fr` → `EN → FR` (affichage neutre, indépendant de la locale UI). */
    private function languages(string $pair): string
    {
        $parts = explode('>', $pair);

        return 2 === \count($parts)
            ? strtoupper($parts[0]).' → '.strtoupper($parts[1])
            : strtoupper($pair);
    }

    /** Les variables vides laissent des trous : on resserre les lignes blanches. */
    private function clean(string $text): string
    {
        $collapsed = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($collapsed);
    }
}
