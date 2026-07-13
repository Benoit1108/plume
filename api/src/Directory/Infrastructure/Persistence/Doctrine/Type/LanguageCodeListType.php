<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\LanguageCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/** Liste de LanguageCode persistée en JSON (tableau de chaînes ISO 639-1). */
final class LanguageCodeListType extends JsonType
{
    public const string NAME = 'language_code_list';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        $codes = [];
        if (\is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof LanguageCode) {
                    $codes[] = $item->toString();
                } elseif (\is_string($item)) {
                    $codes[] = $item;
                }
            }
        }

        return parent::convertToDatabaseValue($codes, $platform);
    }

    /** @return LanguageCode[] */
    public function convertToPHPValue($value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        $codes = [];
        foreach ($decoded as $item) {
            if (\is_string($item)) {
                $codes[] = LanguageCode::fromString($item);
            }
        }

        return $codes;
    }
}
