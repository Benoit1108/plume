<?php

declare(strict_types=1);

namespace App\Drafting\Application;

/**
 * Politique d'accès à la génération PAYANTE (au-delà du garde-fou de coût global `AiBudget`).
 * Certaines sessions/tenants ne doivent JAMAIS déclencher un appel facturé — typiquement les
 * comptes de DÉMONSTRATION publics (vitrine V2), qui restent pleinement fonctionnels via le
 * générateur gratuit `canned`. Consultée par le sélecteur avant de router vers le fournisseur réel.
 */
interface AiGenerationPolicy
{
    /** Faux si le tenant courant ne doit pas déclencher de génération payante (→ repli canned). */
    public function allowsPaidGeneration(): bool;
}
