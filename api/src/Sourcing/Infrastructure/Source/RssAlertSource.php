<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Source;

use App\Sourcing\Application\Source\AlertSource;
use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Domain\CandidateLead\Source;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Source RSS réelle : GET du flux configuré, parsing best-effort des `<item>` (RSS 2.0).
 * Aucun test ne touche le réseau — le `HttpClientInterface` est injecté (MockHttpClient +
 * flux figé en test, patron des adaptateurs M2). Un item malformé est ignoré, jamais propagé.
 */
final class RssAlertSource implements AlertSource
{
    private const int MAX_TITLE = 300; // aligné sur la colonne candidate_lead.title VARCHAR(300)
    private const int MAX_EXCERPT = 2000;
    private const int MAX_URL = 2000;
    private const int MAX_FEED_BYTES = 5_000_000; // borne la taille d'un flux relevé (anti-DoS)

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function fetch(string $feedUrl): iterable
    {
        if ('' === trim($feedUrl)) {
            return;
        }

        try {
            $response = $this->httpClient->request('GET', $feedUrl);
            // Refuse un flux dont la taille annoncée dépasse la borne (anti-DoS ; le cas sans
            // Content-Length reste borné par le timeout global du client + la garde ci-dessous).
            $declared = (int) ($response->getHeaders()['content-length'][0] ?? 0);
            if ($declared > self::MAX_FEED_BYTES) {
                $this->logger->warning('Sourcing: flux RSS trop volumineux, ignoré.', ['bytes' => $declared]);

                return;
            }
            $xml = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('Sourcing: échec de récupération du flux RSS.', ['error' => $e->getMessage()]);

            return;
        }

        if (\strlen($xml) > self::MAX_FEED_BYTES) {
            return;
        }

        $feed = $this->parse($xml);
        if (null === $feed) {
            return;
        }

        foreach ($feed->channel->item ?? [] as $item) {
            $alert = $this->toAlert($item);
            if (null !== $alert) {
                yield $alert;
            }
        }
    }

    private function parse(string $xml): ?\SimpleXMLElement
    {
        if ('' === trim($xml)) {
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $feed = simplexml_load_string($xml);

            return false === $feed ? null : $feed;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function toAlert(\SimpleXMLElement $item): ?ParsedAlert
    {
        $title = $this->clean((string) $item->title, self::MAX_TITLE);
        if ('' === $title) {
            return null; // best-effort : item sans titre exploitable → ignoré.
        }

        $guid = trim((string) $item->guid);
        $link = trim((string) $item->link);
        $externalId = '' !== $guid ? $guid : ('' !== $link ? $link : null);

        $raw = $item->asXML();

        return new ParsedAlert(
            source: Source::RSS->value,
            title: $title,
            organizationName: null, // non porté par RSS standard — renseigné au tri.
            languagePair: null,
            url: $this->safeHttpUrl($link),
            excerpt: $this->clean((string) $item->description, self::MAX_EXCERPT) ?: null,
            externalId: $externalId,
            postedAt: $this->toIso((string) $item->pubDate),
            rawPayload: false === $raw ? null : $raw,
        );
    }

    private function clean(string $value, int $max): string
    {
        $value = trim(html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

        return mb_substr($value, 0, $max);
    }

    /** N'accepte le lien d'un item que s'il est http(s) — anti-XSS (sera rendu en href). */
    private function safeHttpUrl(string $link): ?string
    {
        $link = trim($link);
        if ('' === $link || 1 !== preg_match('#^https?://#i', $link)) {
            return null;
        }

        return mb_substr($link, 0, self::MAX_URL);
    }

    private function toIso(string $pubDate): ?string
    {
        $pubDate = trim($pubDate);
        if ('' === $pubDate) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($pubDate))->format(\DateTimeInterface::ATOM);
        } catch (\Exception) {
            return null;
        }
    }
}
