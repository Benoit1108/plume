<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Persistence\Doctrine\Type;

use App\Drafting\Domain\Template\TemplateId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/** Type DBAL pour le VO TemplateId (persisté en chaîne). */
final class TemplateIdType extends StringType
{
    public const string NAME = 'template_id';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TemplateId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('Expected TemplateId or string.');
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TemplateId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TemplateId) {
            return $value;
        }
        if (\is_string($value)) {
            return TemplateId::fromString($value);
        }

        throw new \InvalidArgumentException('Expected TemplateId or string.');
    }
}
