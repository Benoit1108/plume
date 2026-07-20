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
    private const int MAX_TITLE = 500;
    private const int MAX_EXCERPT = 2000;
    private const int MAX_URL = 2000;

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $feedUrl,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function fetch(): iterable
    {
        if ('' === trim($this->feedUrl)) {
            return;
        }

        try {
            $xml = $this->httpClient->request('GET', $this->feedUrl)->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('Sourcing: échec de récupération du flux RSS.', ['error' => $e->getMessage()]);

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
            url: '' !== $link ? mb_substr($link, 0, self::MAX_URL) : null,
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
