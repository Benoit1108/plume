<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine\Type;

use App\Directory\Domain\Organization\OrganizationId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;

/** Type DBAL pour le VO OrganizationId (persisté en chaîne). */
final class OrganizationIdType extends AbstractStringIdType
{
    public const string NAME = 'organization_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return OrganizationId::class;
    }
}
