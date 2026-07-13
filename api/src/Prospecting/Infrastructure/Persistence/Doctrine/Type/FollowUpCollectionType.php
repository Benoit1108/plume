<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Persistence\Doctrine\Type;

use App\Prospecting\Domain\Lead\FollowUp;
use App\Prospecting\Domain\Lead\FollowUpId;
use App\Prospecting\Domain\Lead\FollowUpStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Collection de FollowUp (entités de l'agrégat) persistée en JSONB sur la ligne Lead
 * (ADR-0012 : l'agrégat est chargé/sauvé en bloc, le domaine reste pur).
 */
final class FollowUpCollectionType extends JsonType
{
    public const string NAME = 'follow_up_collection';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        $rows = [];
        if (\is_array($value)) {
            foreach ($value as $followUp) {
                if (!$followUp instanceof FollowUp) {
                    continue;
                }
                $rows[] = [
                    'id' => $followUp->id()->toString(),
                    'dueAt' => $followUp->dueAt()->format('Y-m-d'),
                    'label' => $followUp->label(),
                    'status' => $followUp->status()->value,
                ];
            }
        }

        return parent::convertToDatabaseValue($rows, $platform);
    }

    /** @return FollowUp[] */
    public function convertToPHPValue($value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        $followUps = [];
        foreach ($decoded as $row) {
            if (!\is_array($row) || !\is_string($row['id'] ?? null) || !\is_string($row['dueAt'] ?? null)) {
                continue;
            }
            $status = \is_string($row['status'] ?? null) ? FollowUpStatus::tryFrom($row['status']) : null;
            if (null === $status) {
                continue;
            }

            $followUps[] = new FollowUp(
                FollowUpId::fromString($row['id']),
                new \DateTimeImmutable($row['dueAt']),
                \is_string($row['label'] ?? null) ? $row['label'] : null,
                $status,
            );
        }

        return $followUps;
    }
}
