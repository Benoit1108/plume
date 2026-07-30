<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\AlertEmailFetcher;
use App\Mailbox\Application\FetchedAlertEmail;
use App\Mailbox\Infrastructure\Token\AccessTokenMinter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Graph (relève d'alertes, ADR-0017 amendé) : lit UNIQUEMENT le dossier dédié
 * (`Plume/Alertes`) — jamais toute la boîte (minimisation RGPD). Résout le dossier par son
 * displayName, puis liste ses messages (borné) en demandant le corps en TEXTE brut via
 * l'en-tête `Prefer: outlook.body-content-type="text"` (Graph renvoie From/Subject/body inline :
 * pas de récupération message par message ni de base64url, contrairement à Gmail).
 *
 * Même patron best-effort que GmailAlertEmailFetcher/OutlookReplyFetcher : toute erreur
 * réseau/parsing est absorbée — on retourne ce qu'on a pu lire, le Scheduler repassera ;
 * jamais d'exception propagée qui bloquerait la boucle. Canal secondaire.
 */
final class OutlookAlertEmailFetcher implements AlertEmailFetcher
{
    private const string FOLDERS_ENDPOINT = 'https://graph.microsoft.com/v1.0/me/mailFolders';
    private const int MAX_MESSAGES = 25;   // borne le volume relevé par passe
    private const int MAX_BODY = 50_000;   // borne la taille du corps conservé (anti-DoS)

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AccessTokenMinter $tokenMinter,
    ) {
    }

    public function fetch(string $refreshTokenPlain, string $label): array
    {
        $accessToken = $this->tokenMinter->mint($refreshTokenPlain);

        $folderId = $this->resolveFolderId($accessToken, $label);
        if (null === $folderId) {
            return []; // dossier pas encore créé côté boîte → rien à relever
        }

        return $this->listMessages($accessToken, $folderId);
    }

    private function resolveFolderId(string $accessToken, string $label): ?string
    {
        try {
            $data = $this->httpClient->request('GET', self::FOLDERS_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'query' => [
                    '$filter' => sprintf("displayName eq '%s'", str_replace("'", "''", $label)),
                    '$select' => 'id',
                    '$top' => '1',
                ],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface) {
            return null;
        }

        $folders = $data['value'] ?? null;
        if (!\is_array($folders) || !isset($folders[0]) || !\is_array($folders[0])) {
            return null;
        }
        $id = $folders[0]['id'] ?? null;

        return \is_string($id) && '' !== $id ? $id : null;
    }

    /** @return FetchedAlertEmail[] */
    private function listMessages(string $accessToken, string $folderId): array
    {
        try {
            $data = $this->httpClient->request('GET', self::FOLDERS_ENDPOINT.'/'.urlencode($folderId).'/messages', [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    // Force le corps en texte brut (jamais de HTML côté Plume).
                    'Prefer' => 'outlook.body-content-type="text"',
                ],
                'query' => [
                    '$select' => 'from,subject,body,bodyPreview',
                    '$top' => (string) self::MAX_MESSAGES,
                ],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface) {
            return [];
        }

        $messages = $data['value'] ?? null;
        if (!\is_array($messages)) {
            return [];
        }

        $emails = [];
        foreach ($messages as $message) {
            if (\is_array($message)) {
                $emails[] = $this->toAlertEmail($message);
            }
        }

        return array_values(array_filter($emails));
    }

    /** @param array<mixed, mixed> $message */
    private function toAlertEmail(array $message): ?FetchedAlertEmail
    {
        $externalId = $message['id'] ?? null;
        if (!\is_string($externalId) || '' === $externalId) {
            return null; // sans id stable, pas de dédoublonnage possible → on ignore
        }

        return new FetchedAlertEmail(
            fromAddress: $this->fromAddress($message),
            subject: \is_string($message['subject'] ?? null) ? trim($message['subject']) : '',
            body: mb_substr($this->body($message), 0, self::MAX_BODY),
            externalId: $externalId,
        );
    }

    /** @param array<mixed, mixed> $message */
    private function fromAddress(array $message): string
    {
        $from = $message['from'] ?? null;
        $emailAddress = \is_array($from) ? ($from['emailAddress'] ?? null) : null;
        $address = \is_array($emailAddress) ? ($emailAddress['address'] ?? null) : null;

        return \is_string($address) ? trim($address) : '';
    }

    /** @param array<mixed, mixed> $message */
    private function body(array $message): string
    {
        // `Prefer: ...text` demandé → body.content est du texte ; on retombe sur bodyPreview sinon.
        $body = $message['body'] ?? null;
        $content = \is_array($body) ? ($body['content'] ?? null) : null;
        if (\is_string($content) && '' !== trim($content)) {
            return trim($content);
        }

        $preview = $message['bodyPreview'] ?? null;

        return \is_string($preview) ? trim($preview) : '';
    }
}
