<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Prospecting\Infrastructure\ApiResource\State\TodayProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/** L'écran « Aujourd'hui » : relances dues, pistes à contacter, progression hebdo. */
#[ApiResource(
    shortName: 'Today',
    normalizationContext: ['groups' => ['today:read', 'lead:read']],
    operations: [
        new Get(uriTemplate: '/today', provider: TodayProvider::class),
    ],
)]
final class TodayResource
{
    /** Ressource singleton (une par tenant courant). */
    #[ApiProperty(identifier: true)]
    #[Groups(['today:read'])]
    public string $id = 'today';

    /** @var LeadResource[] */
    #[Groups(['today:read'])]
    public array $followUpsDue = [];

    /** @var LeadResource[] */
    #[Groups(['today:read'])]
    public array $toContact = [];

    #[Groups(['today:read'])]
    public int $weeklyTarget = 0;

    #[Groups(['today:read'])]
    public int $weeklyDone = 0;

    #[Groups(['today:read'])]
    public int $streak = 0;
}
