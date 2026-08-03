<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Budget;

use App\Drafting\Application\AiBudget;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;

/**
 * Compteur de consommation IA durable (table `ai_usage`, hors tenant / hors RLS — comme audit_log).
 * Agrégat par MOIS (`YYYY-MM`) : jetons entrée+sortie + nombre d'appels. Upsert atomique
 * (`ON CONFLICT`) pour rester correct sous la concurrence des workers.
 *
 * Politique : coupe-circuit (`AI_GENERATION_ENABLED=0` → jamais d'appel payant) ; plafond mensuel
 * `AI_MONTHLY_TOKEN_BUDGET` (jetons entrée+sortie ; 0 = illimité). Fail-OPEN en lecture : si le
 * compteur est illisible, on n'empêche PAS la génération (le coupe-circuit reste la garantie dure).
 */
final class DoctrineAiBudget implements AiBudget
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly bool $enabled,
        private readonly int $monthlyTokenBudget,
    ) {
    }

    public function allowsGeneration(): bool
    {
        if (!$this->enabled) {
            return false; // coupe-circuit baissé
        }
        if ($this->monthlyTokenBudget <= 0) {
            return true; // pas de plafond configuré
        }

        return $this->periodTokens() < $this->monthlyTokenBudget;
    }

    public function record(int $inputTokens, int $outputTokens): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO ai_usage (period, input_tokens, output_tokens, calls, updated_at)
                VALUES (:period, :in, :out, 1, :now)
                ON CONFLICT (period) DO UPDATE SET
                    input_tokens = ai_usage.input_tokens + excluded.input_tokens,
                    output_tokens = ai_usage.output_tokens + excluded.output_tokens,
                    calls = ai_usage.calls + 1,
                    updated_at = excluded.updated_at
                SQL,
            [
                'period' => $this->period(),
                'in' => max(0, $inputTokens),
                'out' => max(0, $outputTokens),
                'now' => $this->clock->now()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function snapshot(): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT input_tokens, output_tokens, calls FROM ai_usage WHERE period = :period',
            ['period' => $this->period()],
        );

        $input = \is_array($row) && is_numeric($row['input_tokens'] ?? null) ? (int) $row['input_tokens'] : 0;
        $output = \is_array($row) && is_numeric($row['output_tokens'] ?? null) ? (int) $row['output_tokens'] : 0;
        $calls = \is_array($row) && is_numeric($row['calls'] ?? null) ? (int) $row['calls'] : 0;

        return [
            'enabled' => $this->enabled,
            'monthlyTokenBudget' => $this->monthlyTokenBudget,
            'periodTokens' => $input + $output,
            'calls' => $calls,
        ];
    }

    private function periodTokens(): int
    {
        try {
            $value = $this->connection->fetchOne(
                'SELECT COALESCE(input_tokens + output_tokens, 0) FROM ai_usage WHERE period = :period',
                ['period' => $this->period()],
            );
        } catch (\Throwable) {
            return 0; // fail-open : un compteur illisible ne bloque pas (le coupe-circuit garde le contrôle)
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    private function period(): string
    {
        return $this->clock->now()->format('Y-m');
    }
}
