<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\StringId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Type DBAL générique pour un VO d'ID persisté en chaîne (dette ADR-0022 §2 : un seul type au lieu
 * d'un par agrégat). Chaque sous-type ne déclare que son nom et sa classe de VO.
 */
abstract class AbstractStringIdType extends StringType
{
    /** @return class-string<StringId> */
    abstract protected function idClass(): string;

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof StringId) {
            return $value->toString();
        }
        if (\is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('Expected %s or string.', $this->idClass()));
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?StringId
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof StringId) {
            return $value;
        }
        if (\is_string($value)) {
            return ($this->idClass())::fromString($value);
        }

        throw new \InvalidArgumentException(\sprintf('Expected %s or string.', $this->idClass()));
    }
}
