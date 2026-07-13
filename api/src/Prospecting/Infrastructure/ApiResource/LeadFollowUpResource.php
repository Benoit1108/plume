<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Prospecting\Infrastructure\ApiResource\State\ScheduleFollowUpProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Planification (ou replanification) manuelle de la relance d'une piste. */
#[ApiResource(
    shortName: 'LeadFollowUp',
    normalizationContext: ['groups' => ['lead:read']],
    denormalizationContext: ['groups' => ['lead_follow_up:write']],
    operations: [
        new Post(
            uriTemplate: '/leads/{leadId}/schedule-follow-up',
            uriVariables: ['leadId' => new Link(fromClass: LeadResource::class)],
            processor: ScheduleFollowUpProcessor::class,
            output: LeadResource::class,
            status: 200,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Planifier / replanifier la relance'),
        ),
    ],
)]
final class LeadFollowUpResource
{
    /** Échéance (jour) au format Y-m-d. */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Date attendue au format AAAA-MM-JJ.')]
    #[Groups(['lead_follow_up:write'])]
    public string $dueAt = '';

    #[Assert\Length(max: 255)]
    #[Groups(['lead_follow_up:write'])]
    public ?string $label = null;
}
