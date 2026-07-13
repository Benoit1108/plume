<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization;

enum OrganizationType: string
{
    case PUBLISHER = 'PUBLISHER';       // Maison d'édition
    case AV_STUDIO = 'AV_STUDIO';       // Labo audiovisuel / doublage / sous-titrage
    case AGENCY = 'AGENCY';             // Agence de traduction (LSP)
    case OTHER = 'OTHER';
}
