<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
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
        new GetCollection(provider: OrganizationProvider::class),
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
    #[Groups(['org:read', 'org:write'])]
    public string $name = '';

    #[Assert\Choice(['PUBLISHER', 'AV_STUDIO', 'AGENCY', 'OTHER'])]
    #[Groups(['org:read', 'org:write'])]
    public string $type = 'OTHER';

    #[Groups(['org:read', 'org:write'])]
    public ?string $website = null;

    #[Assert\Length(exactly: 2)]
    #[Groups(['org:read', 'org:write'])]
    public ?string $country = null;

    /** @var string[] */
    #[Groups(['org:read', 'org:write'])]
    public array $workingLanguages = [];

    /** @var string[] */
    #[Groups(['org:read', 'org:write'])]
    public array $segments = [];

    #[Groups(['org:read', 'org:write'])]
    public ?string $notes = null;

    #[Groups(['org:read', 'org:write'])]
    public bool $doNotContact = false;

    /** @var ContactResource[] */
    #[Groups(['org:read'])]
    public array $contacts = [];
}
