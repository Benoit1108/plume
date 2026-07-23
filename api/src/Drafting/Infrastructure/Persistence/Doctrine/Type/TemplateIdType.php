<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Persistence\Doctrine\Type;

use App\Drafting\Domain\Template\TemplateId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\AbstractStringIdType;

/** Type DBAL pour le VO TemplateId (persisté en chaîne). */
final class TemplateIdType extends AbstractStringIdType
{
    public const string NAME = 'template_id';

    public function getName(): string
    {
        return self::NAME;
    }

    protected function idClass(): string
    {
        return TemplateId::class;
    }
}
