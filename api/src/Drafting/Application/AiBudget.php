<?php

declare(strict_types=1);

namespace App\Drafting\Application;

/**
 * Garde-fou de coût de l'IA (générative). Deux protections GLOBALES (tous tenants confondus),
 * au-delà du plafond par tenant/heure (rate-limiter) et du défaut gratuit (canned) :
 *  - un COUPE-CIRCUIT (kill-switch) pour désactiver instantanément l'appel payant ;
 *  - un PLAFOND mensuel de jetons : au-delà, on ne facture plus (repli sur le générateur gratuit).
 *
 * `allowsGeneration()` est consulté AVANT d'appeler le fournisseur ; `record()` comptabilise les
 * jetons consommés APRÈS chaque appel réel. Le compteur est durable (hors tenant, hors RLS).
 */
interface AiBudget
{
    /** Faux si le coupe-circuit est baissé ou le plafond mensuel atteint → repli gratuit. */
    public function allowsGeneration(): bool;

    public function record(int $inputTokens, int $outputTokens): void;

    /**
     * Instantané pour le back-office (période courante).
     *
     * @return array{enabled: bool, monthlyTokenBudget: int, periodTokens: int, calls: int}
     */
    public function snapshot(): array;
}
