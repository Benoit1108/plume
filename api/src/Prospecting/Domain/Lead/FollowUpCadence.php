<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

use App\Shared\Domain\Exception\InvalidValue;

/**
 * Cadence de relance : la SÉQUENCE des délais (en jours) entre chaque étape — J+7 après le contact,
 * puis J+21 après la 1re relance, etc. (défaut historique M1.3 : [7, 21, 45]). Désormais
 * CONFIGURABLE par la traductrice (V2.3) : chaque délai est relatif à l'action précédente (non
 * cumulatif), 1..365 j, au plus 10 étapes. Une séquence vide = aucune relance auto (planification
 * 100 % manuelle). Au-delà de la dernière étape, la planification redevient manuelle.
 */
final class FollowUpCadence
{
    /** @var int[] délais en jours, indexés par nombre de relances déjà faites */
    public const array DEFAULT_DAYS = [7, 21, 45];

    private const int MAX_STEPS = 10;
    private const int MAX_DAYS = 365;

    /** @param int[] $days */
    private function __construct(private readonly array $days)
    {
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_DAYS);
    }

    /**
     * Construit depuis une liste de jours saisie (API / config), en VALIDANT (échec = InvalidValue).
     *
     * @param int[] $days
     */
    public static function fromDays(array $days): self
    {
        $days = array_values($days);
        if (\count($days) > self::MAX_STEPS) {
            throw InvalidValue::because(\sprintf('A follow-up cadence has at most %d steps.', self::MAX_STEPS));
        }
        foreach ($days as $delay) {
            if ($delay < 1 || $delay > self::MAX_DAYS) {
                throw InvalidValue::because(\sprintf('Each follow-up delay must be between 1 and %d days.', self::MAX_DAYS));
            }
        }

        return new self($days);
    }

    /**
     * Version TOLÉRANTE pour la LECTURE (config stockée éventuellement corrompue) : filtre les
     * valeurs invalides et retombe sur le défaut si rien d'exploitable — la planification ne doit
     * JAMAIS casser à cause d'une config abîmée.
     *
     * @param int[] $days
     */
    public static function fromStoredDays(array $days): self
    {
        $clean = array_values(array_filter(
            $days,
            static fn (int $d): bool => $d >= 1 && $d <= self::MAX_DAYS,
        ));

        return [] === $clean ? self::default() : new self(\array_slice($clean, 0, self::MAX_STEPS));
    }

    public function nextDelayInDays(int $followUpsDone): ?int
    {
        return $this->days[$followUpsDone] ?? null;
    }

    /** @return int[] */
    public function days(): array
    {
        return $this->days;
    }
}
