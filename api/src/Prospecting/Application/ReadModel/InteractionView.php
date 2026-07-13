<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Une entrée du journal d'interactions (timeline de la fiche piste). */
final class InteractionView
{
    /** @param array<string, mixed> $payload données spécifiques au type (texte de note, statuts…) */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $payload,
        public readonly \DateTimeImmutable $occurredOn,
    ) {
    }
}
