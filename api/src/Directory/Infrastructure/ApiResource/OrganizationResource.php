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
use Symfony\Component\Validator\Constraints as Assert;

/** Resource API (DTO) du Répertoire — jamais l'agrégat/entité Doctrine. */
#[ApiResource(
    shortName: 'Organization',
    operations: [
        new GetCollection(provider: OrganizationProvider::class),
        new Get(provider: OrganizationProvider::class),
        new Post(processor: OrganizationProcessor::class),
        new Patch(provider: OrganizationProvider::class, processor: OrganizationProcessor::class),
    ],
)]
final class OrganizationResource
{
    public ?string $id = null;

    #[Assert\NotBlank]
    public string $name = '';

    #[Assert\Choice(['PUBLISHER', 'AV_STUDIO', 'AGENCY', 'OTHER'])]
    public string $type = 'OTHER';

    public ?string $website = null;

    #[Assert\Length(exactly: 2)]
    public ?string $country = null;

    /** @var string[] */
    public array $workingLanguages = [];

    /** @var string[] */
    public array $segments = [];

    public ?string $notes = null;

    public bool $doNotContact = false;

    /** @var ContactResource[] */
    public array $contacts = [];
}
