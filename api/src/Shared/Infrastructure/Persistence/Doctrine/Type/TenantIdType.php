<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\TenantId;

/** Type DBAL pour le VO TenantId (persisté en chaîne). */
final class TenantIdType extends AbstractStringIdType
{
    public const string NAME = 'tenant_id';

    protected function idClass(): string
    {
        return TenantId::class;
    }
}
