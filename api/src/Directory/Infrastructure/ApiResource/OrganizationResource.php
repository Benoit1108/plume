<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Directory\Infrastructure\ApiResource\State\OrganizationProcessor;
use App\Directory\Infrastructure\ApiResource\State\OrganizationProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Resource API (DTO) du Répertoire — jamais l'agrégat/entité Doctrine. */
#[ApiResource(
    shortName: 'Organization',
    normalizationContext: ['groups' => ['org:read']],
    denormalizationContext: ['groups' => ['org:write']],
    operations: [
        new GetCollection(
            provider: OrganizationProvider::class,
            parameters: [
                'type' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['PUBLISHER', 'AV_STUDIO', 'AGENCY', 'OTHER']], description: "Filtre par type d'organisation"),
                'q' => new QueryParameter(schema: ['type' => 'string'], description: 'Recherche sur le nom (insensible à la casse)'),
                'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], description: 'Page de résultats'),
                'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30], description: 'Taille de page (max 100)'),
            ],
        ),
        new Get(provider: OrganizationProvider::class),
        new Post(processor: OrganizationProcessor::class),
        new Patch(provider: OrganizationProvider::class, processor: OrganizationProcessor::class),
    ],
)]
final class OrganizationResource
{
    #[Groups(['org:read'])]
    public ?string $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['org:read', 'org:write'])]
    public string $name = '';

    #[Assert\Choice(['PUBLISHER', 'AV_STUDIO', 'AGENCY', 'OTHER'])]
    #[Groups(['org:read', 'org:write'])]
    public string $type = 'OTHER';

    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['org:read', 'org:write'])]
    public ?string $website = null;

    #[Assert\Country]
    #[Groups(['org:read', 'org:write'])]
    public ?string $country = null;

    /** @var string[] */
    #[Assert\All([new Assert\Regex(pattern: '/^[a-zA-Z]{2}$/', message: 'Code langue ISO 639-1 attendu (ex. fr).')])]
    #[Groups(['org:read', 'org:write'])]
    public array $workingLanguages = [];

    /** @var string[] */
    #[Assert\All([new Assert\Choice(['PUBLISHING', 'AUDIOVISUAL', 'TECHNICAL', 'OTHER'])])]
    #[Groups(['org:read', 'org:write'])]
    public array $segments = [];

    #[Assert\Length(max: 10000)]
    #[Groups(['org:read', 'org:write'])]
    public ?string $notes = null;

    #[Groups(['org:read', 'org:write'])]
    public bool $doNotContact = false;

    /** @var ContactResource[] */
    #[Groups(['org:read'])]
    public array $contacts = [];
}
