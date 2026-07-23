<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Persistence\Doctrine\Type;

use App\Drafting\Domain\Draft\DraftId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;

/** Type DBAL pour le VO DraftId (persisté en chaîne). */
final class DraftIdType extends AbstractStringIdType
{
    public const string NAME = 'draft_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return DraftId::class;
    }
}
