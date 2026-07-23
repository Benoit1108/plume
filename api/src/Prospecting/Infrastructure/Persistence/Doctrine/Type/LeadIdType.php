<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\Doctrine\Type;

use App\Prospecting\Domain\Lead\LeadId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;

/** Type DBAL pour le VO LeadId (persisté en chaîne). */
final class LeadIdType extends AbstractStringIdType
{
    public const string NAME = 'lead_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return LeadId::class;
    }
}
