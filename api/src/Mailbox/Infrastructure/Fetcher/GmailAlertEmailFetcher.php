<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\AlertEmailFetcher;
use App\Mailbox\Application\FetchedAlertEmail;
use App\Mailbox\Infrastructure\Token\AccessTokenMinter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Gmail (relève d'alertes, ADR-0017 amendé) : lit UNIQUEMENT les emails portant le label
 * dédié — jamais toute la boîte (minimisation RGPD). Résout le label par son nom, liste les
 * messages du label (borné), puis récupère From/Subject/corps texte de chacun.
 *
 * HTTP fin (pas de `google/apiclient`), même patron que GmailReplyFetcher/GmailMailSender.
 * Canal secondaire : toute erreur réseau/parsing est absorbée (best-effort) — on retourne ce
 * qu'on a pu lire, le Scheduler repassera ; jamais d'exception propagée qui bloquerait la boucle.
 */
final class GmailAlertEmailFetcher implements AlertEmailFetcher
{
    private const string LABELS_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/labels';
    private const string LIST_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/messages';
    private const string MESSAGE_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/%s?format=full';
    private const int MAX_MESSAGES = 25;      // borne le volume relevé par passe
    private const int MAX_BODY = 50_000;      // borne la taille du corps conservé (anti-DoS)

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AccessTokenMinter $tokenMinter,
    ) {
    }

    public function fetch(string $refreshTokenPlain, string $label): array
    {
        $accessToken = $this->tokenMinter->mint($refreshTokenPlain);

        $labelId = $this->resolveLabelId($accessToken, $label);
        if (null === $labelId) {
            return []; // label pas encore créé côté boîte → rien à relever
        }

        $emails = [];
        foreach ($this->listMessageIds($accessToken, $labelId) as $messageId) {
            $email = $this->fetchMessage($accessToken, $messageId);
            if (null !== $email) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    private function resolveLabelId(string $accessToken, string $label): ?string
    {
        try {
            $data = $this->httpClient->request('GET', self::LABELS_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface) {
            return null;
        }

        $labels = $data['labels'] ?? null;
        if (!\is_array($labels)) {
            return null;
        }
        foreach ($labels as $entry) {
            if (\is_array($entry) && ($entry['name'] ?? null) === $label && \is_string($entry['id'] ?? null)) {
                return $entry['id'];
            }
        }

        return null;
    }

    /** @return list<string> */
    private function listMessageIds(string $accessToken, string $labelId): array
    {
        try {
            $data = $this->httpClient->request('GET', self::LIST_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'query' => ['labelIds' => $labelId, 'maxResults' => self::MAX_MESSAGES],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface) {
            return [];
        }

        $messages = $data['messages'] ?? null;
        if (!\is_array($messages)) {
            return [];
        }
        $ids = [];
        foreach ($messages as $message) {
            if (\is_array($message) && \is_string($message['id'] ?? null)) {
                $ids[] = $message['id'];
            }
        }

        return $ids;
    }

    private function fetchMessage(string $accessToken, string $messageId): ?FetchedAlertEmail
    {
        try {
            $message = $this->httpClient->request('GET', sprintf(self::MESSAGE_ENDPOINT, urlencode($messageId)), [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface) {
            return null; // message supprimé/illisible → on l'ignore
        }

        $payload = $message['payload'] ?? null;
        if (!\is_array($payload)) {
            return null;
        }

        $from = $this->header($payload, 'From');
        $subject = $this->header($payload, 'Subject');
        $body = $this->extractBody($payload);
        if ('' === $body) {
            $snippet = $message['snippet'] ?? null;
            $body = \is_string($snippet) ? html_entity_decode($snippet, \ENT_QUOTES | \ENT_HTML5, 'UTF-8') : '';
        }

        return new FetchedAlertEmail(
            fromAddress: $from,
            subject: $subject,
            body: mb_substr($body, 0, self::MAX_BODY),
            externalId: $messageId, // stable → dédoublonnage à l'ingestion
        );
    }

    /** @param array<mixed, mixed> $payload */
    private function header(array $payload, string $name): string
    {
        $headers = $payload['headers'] ?? null;
        if (!\is_array($headers)) {
            return '';
        }
        foreach ($headers as $header) {
            if (\is_array($header) && ($header['name'] ?? null) === $name && \is_string($header['value'] ?? null)) {
                return trim($header['value']);
            }
        }

        return '';
    }

    /**
     * Corps texte : text/plain en priorité, en descendant récursivement les parts multipart.
     *
     * @param array<mixed, mixed> $part
     */
    private function extractBody(array $part): string
    {
        $mimeType = \is_string($part['mimeType'] ?? null) ? $part['mimeType'] : '';

        if ('text/plain' === $mimeType) {
            return $this->decodePartData($part);
        }

        $parts = $part['parts'] ?? null;
        if (\is_array($parts)) {
            foreach ($parts as $child) {
                if (\is_array($child)) {
                    $body = $this->extractBody($child);
                    if ('' !== $body) {
                        return $body;
                    }
                }
            }
        }

        return '';
    }

    /** @param array<mixed, mixed> $part */
    private function decodePartData(array $part): string
    {
        $body = $part['body'] ?? null;
        $data = \is_array($body) ? ($body['data'] ?? null) : null;
        if (!\is_string($data) || '' === $data) {
            return '';
        }

        // Gmail encode en base64url (RFC 4648) SANS padding : on normalise puis on rétablit le
        // padding avant décodage (strict=true rejette un corps corrompu).
        $normalized = strtr($data, '-_', '+/');
        $padded = str_pad($normalized, (int) (ceil(\strlen($normalized) / 4) * 4), '=');
        $decoded = base64_decode($padded, true);

        return false === $decoded ? '' : trim($decoded);
    }
}
