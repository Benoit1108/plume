<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\Segment;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/** Liste de Segment persistée en JSON (tableau de valeurs d'enum). */
final class SegmentListType extends JsonType
{
    public const string NAME = 'segment_list';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        $values = [];
        if (\is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof Segment) {
                    $values[] = $item->value;
                } elseif (\is_string($item)) {
                    $values[] = $item;
                }
            }
        }

        return parent::convertToDatabaseValue($values, $platform);
    }

    /** @return Segment[] */
    public function convertToPHPValue($value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        $segments = [];
        foreach ($decoded as $item) {
            if (\is_string($item)) {
                $segments[] = Segment::from($item);
            }
        }

        return $segments;
    }
}
