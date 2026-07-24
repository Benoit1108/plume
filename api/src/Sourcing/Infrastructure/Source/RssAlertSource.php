<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Source;

use App\Sourcing\Application\Source\AlertSource;
use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Domain\CandidateLead\Source;
use Laminas\Feed\Reader\Entry\AbstractEntry;
use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Reader;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Source de flux réelle : GET du flux configuré, parsing best-effort des entrées.
 *
 * Le parsing délègue à **laminas-feed** (`Reader`), qui lit indifféremment **RSS 2.0** (`item`)
 * ET **Atom** (`entry`) derrière une interface uniforme — l'ancien parsing maison ne lisait que
 * `channel->item` et renvoyait zéro annonce, silencieusement, sur un flux Atom (ADR de rétro).
 *
 * L'I/O réseau reste SOUS NOTRE contrôle : on récupère le corps via le `HttpClientInterface`
 * injecté (gardé anti-SSRF + borné en taille), puis on passe la CHAÎNE à `Reader::importString`.
 * On n'utilise JAMAIS `Reader::import($uri)` (qui ferait sa propre requête, hors garde SSRF).
 * Aucun test ne touche le réseau ; une entrée malformée est ignorée, jamais propagée.
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
                $this->logger->warning('Sourcing: flux trop volumineux, ignoré.', ['bytes' => $declared]);

                return;
            }
            $xml = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('Sourcing: échec de récupération du flux.', ['error' => $e->getMessage()]);

            return;
        }

        if ('' === trim($xml) || \strlen($xml) > self::MAX_FEED_BYTES) {
            return;
        }

        try {
            $feed = Reader::importString($xml); // RSS 2.0 et Atom, transparent
        } catch (\Throwable $e) {
            $this->logger->warning('Sourcing: flux illisible (ni RSS ni Atom).', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($feed as $entry) {
            $alert = $this->toAlert($entry);
            if (null !== $alert) {
                yield $alert;
            }
        }
    }

    private function toAlert(EntryInterface $entry): ?ParsedAlert
    {
        $title = $this->clean((string) $entry->getTitle(), self::MAX_TITLE);
        if ('' === $title) {
            return null; // best-effort : entrée sans titre exploitable → ignorée.
        }

        $link = trim((string) $entry->getLink());
        $id = trim((string) $entry->getId());
        $externalId = '' !== $id ? $id : ('' !== $link ? $link : null);

        // description (RSS) / summary (Atom), via l'interface uniforme laminas.
        $excerpt = $this->clean((string) $entry->getDescription(), self::MAX_EXCERPT);

        return new ParsedAlert(
            source: Source::RSS->value,
            title: $title,
            organizationName: null, // non porté par les flux standard — renseigné au tri.
            languagePair: null,
            url: $this->safeHttpUrl($link),
            excerpt: '' !== $excerpt ? $excerpt : null,
            externalId: $externalId,
            postedAt: $this->postedAt($entry),
            rawPayload: $this->rawXml($entry),
        );
    }

    private function clean(string $value, int $max): string
    {
        // Décoder les entités PUIS strip_tags (sinon une balise encodée ressort décodée en texte).
        $value = trim(strip_tags(html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));

        return mb_substr($value, 0, $max);
    }

    /** N'accepte le lien d'une entrée que s'il est http(s) — anti-XSS (sera rendu en href). */
    private function safeHttpUrl(string $link): ?string
    {
        $link = trim($link);
        if ('' === $link || 1 !== preg_match('#^https?://#i', $link)) {
            return null;
        }

        return mb_substr($link, 0, self::MAX_URL);
    }

    private function postedAt(EntryInterface $entry): ?string
    {
        $date = $entry->getDateModified() ?? $entry->getDateCreated();

        return $date?->format(\DateTimeInterface::ATOM);
    }

    private function rawXml(EntryInterface $entry): ?string
    {
        // saveXml() n'est pas sur l'interface mais sur l'implémentation concrète (RSS comme Atom).
        if (!$entry instanceof AbstractEntry) {
            return null;
        }
        try {
            $xml = trim((string) $entry->saveXml());

            return '' !== $xml ? $xml : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
