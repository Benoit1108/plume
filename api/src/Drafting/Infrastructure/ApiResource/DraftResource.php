<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Drafting\Infrastructure\ApiResource\State\DraftProcessor;
use App\Drafting\Infrastructure\ApiResource\State\DraftProvider;
use App\Prospecting\Infrastructure\ApiResource\LeadResource;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Brouillon généré (Rédaction assistée) — DRAFT-FIRST : relu, édité, copié,
 * jamais envoyé depuis ce contexte (M2). Génération asynchrone : le POST rend
 * un brouillon GENERATING, le worker le fait passer READY ou FAILED.
 */
#[ApiResource(
    shortName: 'Draft',
    normalizationContext: ['groups' => ['draft:read']],
    operations: [
        new GetCollection(
            uriTemplate: '/leads/{leadId}/drafts',
            uriVariables: ['leadId' => new Link(fromClass: LeadResource::class)],
            provider: DraftProvider::class,
        ),
        new Post(
            uriTemplate: '/leads/{leadId}/drafts',
            uriVariables: ['leadId' => new Link(fromClass: LeadResource::class)],
            denormalizationContext: ['groups' => ['draft:create']],
            processor: DraftProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Générer un brouillon (asynchrone)'),
        ),
        new Get(uriTemplate: '/drafts/{id}', provider: DraftProvider::class),
        new Patch(
            uriTemplate: '/drafts/{id}',
            denormalizationContext: ['groups' => ['draft:edit']],
            provider: DraftProvider::class,
            processor: DraftProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Éditer le brouillon (READY uniquement)'),
        ),
        new Post(
            uriTemplate: '/drafts/{id}/regenerate',
            name: 'draft_regenerate',
            input: false,
            status: 200,
            provider: DraftProvider::class,
            processor: DraftProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Relancer la génération'),
        ),
        new Delete(uriTemplate: '/drafts/{id}', provider: DraftProvider::class, processor: DraftProcessor::class),
    ],
)]
final class DraftResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['draft:read'])]
    public ?string $id = null;

    #[Groups(['draft:read'])]
    public string $leadId = '';

    #[Assert\NotBlank]
    #[Assert\Choice(['APPLICATION_EMAIL', 'COVER_LETTER', 'FOLLOW_UP_EMAIL'])]
    #[Groups(['draft:read', 'draft:create'])]
    public string $type = 'APPLICATION_EMAIL';

    /** Langue du prospect (ADR-0011), pas celle de l'UI. */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}$/', message: 'Code langue ISO 639-1 attendu (2 lettres).')]
    #[Groups(['draft:read', 'draft:create'])]
    public string $targetLanguage = '';

    #[Assert\Length(max: 36)]
    #[Assert\Regex(pattern: '/^[0-9a-fA-F-]{36}$/', message: 'Identifiant de modèle invalide.')]
    #[Groups(['draft:read', 'draft:create'])]
    public ?string $templateId = null;

    #[Assert\Length(max: 255)]
    #[Groups(['draft:read', 'draft:edit'])]
    public ?string $subject = null;

    /** Borné : le corps est stocké en TEXT et peut repartir dans un prompt. */
    #[Assert\Length(max: 20000)]
    #[Groups(['draft:read', 'draft:edit'])]
    public string $body = '';

    #[Groups(['draft:read'])]
    #[ApiProperty(openapiContext: ['enum' => ['GENERATING', 'READY', 'FAILED']])]
    public string $status = 'GENERATING';

    /** Code de raison (i18n côté front), jamais un message interne. */
    #[Groups(['draft:read'])]
    public ?string $failureReason = null;

    #[Groups(['draft:read'])]
    public ?string $createdAt = null;

    #[Groups(['draft:read'])]
    public ?string $updatedAt = null;
}
