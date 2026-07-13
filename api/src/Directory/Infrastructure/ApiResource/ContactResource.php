<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Directory\Infrastructure\ApiResource\State\ContactProcessor;
use App\Directory\Infrastructure\ApiResource\State\ContactProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** DTO d'un contact : imbriqué (org:read) + sous-endpoints sous une organisation. */
#[ApiResource(
    shortName: 'Contact',
    normalizationContext: ['groups' => ['contact:read']],
    denormalizationContext: ['groups' => ['contact:write']],
    operations: [
        new Post(
            uriTemplate: '/organizations/{organizationId}/contacts',
            uriVariables: ['organizationId' => new Link(fromClass: OrganizationResource::class)],
            processor: ContactProcessor::class,
        ),
        new Patch(
            uriTemplate: '/organizations/{organizationId}/contacts/{id}',
            uriVariables: [
                'organizationId' => new Link(fromClass: OrganizationResource::class),
                'id' => new Link(fromClass: ContactResource::class),
            ],
            provider: ContactProvider::class,
            processor: ContactProcessor::class,
        ),
        new Delete(
            uriTemplate: '/organizations/{organizationId}/contacts/{id}',
            uriVariables: [
                'organizationId' => new Link(fromClass: OrganizationResource::class),
                'id' => new Link(fromClass: ContactResource::class),
            ],
            provider: ContactProvider::class,
            processor: ContactProcessor::class,
        ),
    ],
)]
final class ContactResource
{
    #[Groups(['org:read', 'contact:read'])]
    public ?string $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['org:read', 'contact:read', 'contact:write'])]
    public string $fullName = '';

    #[Assert\Length(max: 255)]
    #[Groups(['org:read', 'contact:read', 'contact:write'])]
    public ?string $role = null;

    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[Groups(['org:read', 'contact:read', 'contact:write'])]
    public ?string $email = null;

    #[Assert\Length(max: 50)]
    #[Groups(['org:read', 'contact:read', 'contact:write'])]
    public ?string $phone = null;

    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['org:read', 'contact:read', 'contact:write'])]
    public ?string $linkedinUrl = null;

    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}$/', message: 'Code langue ISO 639-1 attendu (ex. fr).')]
    #[Groups(['org:read', 'contact:read', 'contact:write'])]
    public ?string $preferredLanguage = null;

    #[Groups(['org:read', 'contact:read'])]
    public bool $doNotContact = false;
}
