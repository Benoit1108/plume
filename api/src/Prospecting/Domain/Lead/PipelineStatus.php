<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/**
 * Statuts du pipeline (opinioné, figé en V1 — cf. ADR-0008) et règles de transition.
 */
enum PipelineStatus: string
{
    case TO_CONTACT = 'TO_CONTACT';
    case CONTACTED = 'CONTACTED';
    case FOLLOWED_UP = 'FOLLOWED_UP';
    case IN_DISCUSSION = 'IN_DISCUSSION';
    case SAMPLE_TEST = 'SAMPLE_TEST';
    case WON = 'WON';
    case LOST = 'LOST';
    case PAUSED = 'PAUSED';

    /** @return self[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::TO_CONTACT => [self::CONTACTED, self::LOST, self::PAUSED],
            // TO_CONTACT depuis CONTACTED = correction d'un « Contacter » cliqué par erreur (ADR-0008 amendé).
            self::CONTACTED => [self::TO_CONTACT, self::FOLLOWED_UP, self::IN_DISCUSSION, self::LOST, self::PAUSED],
            self::FOLLOWED_UP => [self::FOLLOWED_UP, self::IN_DISCUSSION, self::LOST, self::PAUSED],
            self::IN_DISCUSSION => [self::SAMPLE_TEST, self::WON, self::LOST, self::PAUSED],
            self::SAMPLE_TEST => [self::WON, self::LOST, self::IN_DISCUSSION],
            self::PAUSED => [self::TO_CONTACT, self::CONTACTED, self::FOLLOWED_UP, self::IN_DISCUSSION],
            self::WON, self::LOST => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return \in_array($this, [self::WON, self::LOST], true);
    }
}
