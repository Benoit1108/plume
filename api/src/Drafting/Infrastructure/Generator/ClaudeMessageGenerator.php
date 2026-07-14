<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Generator;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\Exception\GenerationFailed;
use App\Drafting\Application\GeneratedMessage;
use App\Drafting\Application\MessageGenerator;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL vers l'API Messages Anthropic. Tout le vocabulaire « Claude » reste ici :
 * le domaine et l'application ne connaissent que le port MessageGenerator.
 * Modèle piloté par env (DRAFTING_MODEL), sortie bornée, échec → GenerationFailed.
 */
final class ClaudeMessageGenerator implements MessageGenerator
{
    private const string ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const string API_VERSION = '2023-06-01';
    private const int MAX_TOKENS = 1024;
    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
    ) {
    }

    public function generate(DraftPrompt $prompt): GeneratedMessage
    {
        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => self::MAX_TOKENS,
                    'system' => $this->systemPrompt($prompt),
                    'messages' => [
                        ['role' => 'user', 'content' => $this->userPrompt($prompt)],
                    ],
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
            $payload = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw GenerationFailed::because('Anthropic API call failed.', $e);
        }

        $content = $payload['content'] ?? null;
        $block = \is_array($content) ? ($content[0] ?? null) : null;
        $text = \is_array($block) ? ($block['text'] ?? null) : null;
        if (!\is_string($text) || '' === trim($text)) {
            throw GenerationFailed::because('Anthropic API returned an empty message.');
        }

        return $this->interpolateContact($this->parse($text), $prompt);
    }

    /** Interpolation LOCALE du destinataire : la PII reste chez nous. */
    private function interpolateContact(GeneratedMessage $message, DraftPrompt $prompt): GeneratedMessage
    {
        $contact = $prompt->contactName ?? ('fr' === $prompt->targetLanguage ? 'Madame, Monsieur' : 'Madam or Sir');

        return new GeneratedMessage(
            null === $message->subject ? null : str_replace('{{contact}}', $contact, $message->subject),
            str_replace('{{contact}}', $contact, $message->body),
        );
    }

    private function systemPrompt(DraftPrompt $prompt): string
    {
        $language = 'fr' === $prompt->targetLanguage ? 'French' : ('en' === $prompt->targetLanguage ? 'English' : $prompt->targetLanguage);
        $format = 'COVER_LETTER' === $prompt->type
            ? 'Output ONLY the letter body, no subject line, no commentary.'
            : "Output format, nothing else:\nSUBJECT: <subject line>\n<blank line>\n<email body>";
        // RGPD (ADR-0014, dette soldée M2.4) : le NOM du contact ne part jamais
        // chez le sous-traitant — le modèle écrit le littéral {{contact}},
        // interpolé LOCALEMENT après génération.
        $format .= ' Address the recipient with the literal placeholder {{contact}} (it will be replaced locally).';

        return 'You write prospecting messages on behalf of a freelance translator contacting publishers, '
            ."audiovisual studios and agencies. Write in {$language}. Professional, warm, concise (under 180 words), "
            ."no flattery, no invented facts — use ONLY the details provided. End with the signature if provided. {$format}";
    }

    private function userPrompt(DraftPrompt $prompt): string
    {
        $lines = [
            'Message type: '.$prompt->type,
            'Language pair: '.$prompt->languagePair,
            'Target organization: '.$prompt->organizationName.' (segment: '.$prompt->segment.')',
            'Pipeline status: '.$prompt->leadStatus,
        ];
        if (null !== $prompt->bio) {
            $lines[] = 'Translator bio: '.$prompt->bio;
        }
        if (null !== $prompt->specialties) {
            $lines[] = 'Specialties: '.$prompt->specialties;
        }
        if (null !== $prompt->signature) {
            $lines[] = 'Signature: '.$prompt->signature;
        }
        if (null !== $prompt->templateBody) {
            $lines[] = "Use this template as the base, keep its structure and intent:\n"
                .(null !== $prompt->templateSubject ? 'Subject: '.$prompt->templateSubject."\n" : '')
                .$prompt->templateBody;
        }

        return implode("\n", $lines);
    }

    private function parse(string $text): GeneratedMessage
    {
        $text = trim($text);
        if (1 === preg_match('/^(?:SUBJECT|OBJET)\s*:\s*(.+?)\R+(.*)$/su', $text, $matches)) {
            $body = trim($matches[2]);
            if ('' !== $body) {
                // Colonne subject VARCHAR(255) : un sujet hors gabarit ne doit pas
                // faire échouer la persistance (et donc partir en retry).
                return new GeneratedMessage(mb_substr(trim($matches[1]), 0, 255), $body);
            }
        }

        return new GeneratedMessage(null, $text);
    }
}
