<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Directory\Infrastructure\ApiResource\State\OrganizationImportProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Import CSV d'organisations : POST du contenu brut, réponse = récapitulatif.
 * Chaque ligne valide crée une organisation (transaction indépendante) ; une ligne
 * fautive n'annule pas les autres.
 */
#[ApiResource(
    shortName: 'OrganizationImport',
    normalizationContext: ['groups' => ['org_import:read']],
    denormalizationContext: ['groups' => ['org_import:write']],
    operations: [
        new Post(
            uriTemplate: '/organizations/import',
            processor: OrganizationImportProcessor::class,
        ),
    ],
)]
final class OrganizationImportResource
{
    /** Nombre maximal de lignes de données par import (borne anti-abus). */
    public const MAX_ROWS = 1000;

    /** Contenu brut du fichier CSV (borné à ~1 Mo — au-delà, scinder le fichier). */
    #[Assert\NotBlank]
    #[Assert\Length(max: 1_000_000, maxMessage: 'Fichier trop volumineux ({{ value_length }} caractères, max {{ limit }}). Scindez-le en plusieurs imports.')]
    #[Groups(['org_import:write'])]
    public string $content = '';

    /** Délimiteur forcé (`,`, `;` ou tabulation) ; auto-détecté si absent. */
    #[Assert\Choice([',', ';', "\t"])]
    #[Groups(['org_import:write'])]
    public ?string $delimiter = null;

    #[Groups(['org_import:read'])]
    public int $imported = 0;

    #[Groups(['org_import:read'])]
    public int $skipped = 0;

    #[Groups(['org_import:read'])]
    public int $failed = 0;

    /** @var list<array{line: int, message: string}> */
    #[Groups(['org_import:read'])]
    public array $errors = [];
}
