<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Prospecting\Infrastructure\ApiResource\State\DashboardProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Le tableau de bord : taux (numérateurs/dénominateurs en clair), pipeline,
 * activité des 8 dernières semaines, résultats par segment. Lecture seule.
 */
#[ApiResource(
    shortName: 'Dashboard',
    normalizationContext: ['groups' => ['dashboard:read']],
    operations: [
        new Get(uriTemplate: '/dashboard', provider: DashboardProvider::class),
    ],
)]
final class DashboardResource
{
    /** Ressource singleton (une par tenant courant). */
    #[ApiProperty(identifier: true)]
    #[Groups(['dashboard:read'])]
    public string $id = 'dashboard';

    /** Pistes contactées au moins une fois (journal, cumul). */
    #[Groups(['dashboard:read'])]
    public int $contacted = 0;

    /** Pistes ayant reçu au moins une réponse. */
    #[Groups(['dashboard:read'])]
    public int $replied = 0;

    #[Groups(['dashboard:read'])]
    public int $won = 0;

    #[Groups(['dashboard:read'])]
    public int $lost = 0;

    /** Pistes non terminées (ni gagnées ni perdues, pause comprise). */
    #[Groups(['dashboard:read'])]
    public int $activeLeads = 0;

    /** Actes de démarchage (contacts + relances) du mois local en cours. */
    #[Groups(['dashboard:read'])]
    public int $outreachThisMonth = 0;

    #[Groups(['dashboard:read'])]
    public int $weeklyTarget = 0;

    /** @var array<array{status: string, count: int}> répartition par statut (ordre kanban) */
    #[Groups(['dashboard:read'])]
    public array $pipeline = [];

    /** @var array<array{weekStart: string, acts: int}> 8 semaines ISO, la plus ancienne d'abord */
    #[Groups(['dashboard:read'])]
    public array $weeklyActivity = [];

    /** @var array<array{segment: string, contacted: int, replied: int, won: int}> */
    #[Groups(['dashboard:read'])]
    public array $segments = [];
}
