<?php

declare(strict_types=1);

namespace App\Billing\Application;

/**
 * Port du contexte Billing (V2.2). État d'abonnement par compte, filtré explicitement par tenant
 * (table hors RLS : écrite à l'inscription publique — sans tenant — et par les webhooks Stripe).
 *
 * Slice 1 : démarrage d'essai + droit d'accès (garde lecture seule). L'activation/résiliation via
 * Stripe et l'accès offert viendront enrichir ce port (slices suivantes).
 */
interface Subscriptions
{
    /** Démarre un essai (idempotent : ne fait rien si un abonnement existe déjà pour ce tenant). */
    public function startTrial(string $tenantId): void;

    /**
     * Le compte a-t-il le droit d'ÉCRIRE (produit) ? Vrai par défaut si aucun abonnement n'existe
     * (comptes antérieurs à la facturation — grandfathered) ; sinon selon le statut + l'essai.
     */
    public function isEntitled(string $tenantId): bool;
}
