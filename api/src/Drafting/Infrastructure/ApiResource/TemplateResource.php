<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Drafting\Infrastructure\ApiResource\State\TemplateProcessor;
use App\Drafting\Infrastructure\ApiResource\State\TemplateProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modèle (gabarit) de message : canevas réutilisable par type/segment/langue,
 * avec variables ({{contact}}, {{organisation}}, {{langues}}, {{bio}},
 * {{specialites}}, {{signature}}). 3 gabarits seedés à la première utilisation.
 */
#[ApiResource(
    shortName: 'Template',
    normalizationContext: ['groups' => ['template:read']],
    denormalizationContext: ['groups' => ['template:write']],
    operations: [
        new GetCollection(uriTemplate: '/templates', provider: TemplateProvider::class),
        new Post(uriTemplate: '/templates', processor: TemplateProcessor::class),
        new Get(uriTemplate: '/templates/{id}', provider: TemplateProvider::class),
        new Patch(uriTemplate: '/templates/{id}', provider: TemplateProvider::class, processor: TemplateProcessor::class),
        new Delete(uriTemplate: '/templates/{id}', provider: TemplateProvider::class, processor: TemplateProcessor::class),
    ],
)]
final class TemplateResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['template:read'])]
    public ?string $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['template:read', 'template:write'])]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Choice(['APPLICATION_EMAIL', 'COVER_LETTER', 'FOLLOW_UP_EMAIL'])]
    #[Groups(['template:read', 'template:write'])]
    public string $type = 'APPLICATION_EMAIL';

    #[Assert\NotBlank]
    #[Assert\Choice(['PUBLISHING', 'AUDIOVISUAL', 'TECHNICAL', 'OTHER'])]
    #[Groups(['template:read', 'template:write'])]
    public string $segment = 'PUBLISHING';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}$/', message: 'Code langue ISO 639-1 attendu (2 lettres).')]
    #[Groups(['template:read', 'template:write'])]
    public string $language = 'fr';

    #[Assert\Length(max: 255)]
    #[Groups(['template:read', 'template:write'])]
    public ?string $subject = null;

    /** Borné : le gabarit part intégralement dans le prompt de génération. */
    #[Assert\NotBlank]
    #[Assert\Length(max: 20000)]
    #[Groups(['template:read', 'template:write'])]
    public string $body = '';
}
