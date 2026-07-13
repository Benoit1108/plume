<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Prospecting\Infrastructure\ApiResource\State\LeadNoteProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Note manuelle sur une piste — versée au journal d'interactions (via event). */
#[ApiResource(
    shortName: 'LeadNote',
    normalizationContext: ['groups' => ['lead_note:read']],
    denormalizationContext: ['groups' => ['lead_note:write']],
    operations: [
        new Post(
            uriTemplate: '/leads/{leadId}/notes',
            uriVariables: ['leadId' => new Link(fromClass: LeadResource::class)],
            processor: LeadNoteProcessor::class,
        ),
    ],
)]
final class LeadNoteResource
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    #[Groups(['lead_note:read', 'lead_note:write'])]
    public string $text = '';
}
