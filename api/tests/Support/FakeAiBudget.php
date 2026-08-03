<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Drafting\Application\AiBudget;

/** Garde-fou IA factice : autorisation configurable, enregistre les jetons comptabilisés. */
final class FakeAiBudget implements AiBudget
{
    /** @var list<array{int, int}> */
    public array $recorded = [];

    public function __construct(private readonly bool $allows = true)
    {
    }

    public function allowsGeneration(): bool
    {
        return $this->allows;
    }

    public function record(int $inputTokens, int $outputTokens): void
    {
        $this->recorded[] = [$inputTokens, $outputTokens];
    }

    public function snapshot(): array
    {
        return ['enabled' => $this->allows, 'monthlyTokenBudget' => 0, 'periodTokens' => 0, 'calls' => \count($this->recorded)];
    }
}
