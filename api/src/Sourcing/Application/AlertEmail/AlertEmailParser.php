<?php

declare(strict_types=1);

namespace App\Sourcing\Application\AlertEmail;

use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Domain\CandidateLead\Source;

/**
 * Parser d'email d'alerte — **générique** (M3.2) : 1 annonce = 1 email. La provenance est
 * déduite du domaine de l'expéditeur ; le titre vient du sujet, l'extrait du corps, l'URL du
 * 1er lien trouvé. Best-effort : les parsers fins par fournisseur (extraction structurée) sont
 * un suivi, avec de vrais emails. Aucune donnée personnelle n'est extraite automatiquement.
 */
final class AlertEmailParser
{
    private const int MAX_TITLE = 300;
    private const int MAX_EXCERPT = 500;
    private const int MAX_URL = 2000;

    /** @return ParsedAlert[] */
    public function parse(string $fromAddress, string $subject, string $body, string $externalId): array
    {
        $title = trim($subject);
        if ('' === $title) {
            return []; // best-effort : sans sujet exploitable, on n'ingère rien.
        }

        return [new ParsedAlert(
            source: $this->detectSource($fromAddress)->value,
            title: mb_substr($title, 0, self::MAX_TITLE),
            organizationName: null,
            languagePair: null,
            url: $this->firstUrl($body),
            excerpt: $this->snippet($body),
            externalId: '' !== trim($externalId) ? $externalId : null,
            postedAt: null,
            rawPayload: $body,
        )];
    }

    private function detectSource(string $fromAddress): Source
    {
        $from = mb_strtolower($fromAddress);

        return match (true) {
            str_contains($from, 'proz.com') => Source::PROZ,
            str_contains($from, 'linkedin.') => Source::LINKEDIN,
            str_contains($from, 'translatorscafe.') => Source::TRANSLATORSCAFE,
            default => Source::MANUAL, // provenance inconnue → annonce générique (JOB_BOARD)
        };
    }

    private function firstUrl(string $body): ?string
    {
        if (1 === preg_match('#https?://[^\s<>"\')]+#i', $body, $matches)) {
            return mb_substr($matches[0], 0, self::MAX_URL);
        }

        return null;
    }

    private function snippet(string $body): ?string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));

        return '' === $text ? null : mb_substr($text, 0, self::MAX_EXCERPT);
    }
}
