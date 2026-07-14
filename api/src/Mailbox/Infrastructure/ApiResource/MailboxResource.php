<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Mailbox\Infrastructure\ApiResource\State\FetchRepliesProcessor;
use App\Mailbox\Infrastructure\ApiResource\State\MailboxConnectProcessor;
use App\Mailbox\Infrastructure\ApiResource\State\MailboxProvider;
use App\Mailbox\Infrastructure\ApiResource\State\MailboxRevokeProcessor;
use App\Mailbox\Infrastructure\ApiResource\State\OAuthStartProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * La boîte email connectée du tenant (une seule en V1 — D6).
 * Le flux OAuth : POST /mailbox/oauth/start → URL de consentement →
 * le navigateur revient sur le front avec code+state → POST /mailbox/connect.
 * Jamais un token dans une réponse.
 */
#[ApiResource(
    shortName: 'Mailbox',
    normalizationContext: ['groups' => ['mailbox:read']],
    operations: [
        new Get(uriTemplate: '/mailbox', provider: MailboxProvider::class),
        new Post(
            uriTemplate: '/mailbox/oauth/start',
            name: 'mailbox_oauth_start',
            status: 200,
            denormalizationContext: ['groups' => ['mailbox:start']],
            processor: OAuthStartProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: "Démarrer le consentement OAuth (renvoie l'URL d'autorisation)"),
        ),
        new Post(
            uriTemplate: '/mailbox/connect',
            name: 'mailbox_connect',
            denormalizationContext: ['groups' => ['mailbox:connect']],
            processor: MailboxConnectProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Finaliser la connexion (code OAuth + state anti-CSRF)'),
        ),
        new Post(
            uriTemplate: '/mailbox/fetch-replies',
            name: 'mailbox_fetch_replies',
            input: false,
            status: 200,
            provider: MailboxProvider::class,
            processor: FetchRepliesProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Relever les réponses maintenant (le Scheduler le fait toutes les 5 min)'),
        ),
        new Delete(
            uriTemplate: '/mailbox',
            provider: MailboxProvider::class,
            processor: MailboxRevokeProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Déconnecter la boîte (révoque le consentement, efface les tokens)'),
        ),
    ],
)]
final class MailboxResource
{
    /** Ressource singleton (une par tenant courant, V1). */
    #[ApiProperty(identifier: true)]
    #[Groups(['mailbox:read'])]
    public string $id = 'mailbox';

    #[Assert\Choice(['GMAIL', 'OUTLOOK'], groups: ['mailbox_start'])]
    #[Groups(['mailbox:read', 'mailbox:start'])]
    public string $provider = '';

    #[Groups(['mailbox:read'])]
    public string $emailAddress = '';

    /** NONE = aucune boîte connectée (la ressource singleton répond toujours 200). */
    #[Groups(['mailbox:read'])]
    public string $status = 'NONE';

    /** Code de raison (i18n côté front), jamais un message interne. */
    #[Groups(['mailbox:read'])]
    public ?string $failureReason = null;

    #[Groups(['mailbox:read'])]
    public ?string $connectedAt = null;

    #[Groups(['mailbox:read'])]
    public ?string $lastSyncAt = null;

    /** URL de consentement (réponse de /oauth/start uniquement). */
    #[Groups(['mailbox:read'])]
    public ?string $authorizationUrl = null;

    // --- Écriture (connect) ---
    #[Assert\NotBlank(groups: ['mailbox_connect'])]
    #[Assert\Length(max: 512)]
    #[Groups(['mailbox:connect'])]
    public string $code = '';

    #[Assert\NotBlank(groups: ['mailbox_connect'])]
    #[Assert\Length(max: 512)]
    #[Groups(['mailbox:connect'])]
    public string $state = '';
}
