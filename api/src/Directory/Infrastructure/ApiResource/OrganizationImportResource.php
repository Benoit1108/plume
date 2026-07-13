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
    /** Contenu brut du fichier CSV. */
    #[Assert\NotBlank]
    #[Groups(['org_import:write'])]
    public string $content = '';

    /** Délimiteur forcé (`,`, `;` ou tabulation) ; auto-détecté si absent. */
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
