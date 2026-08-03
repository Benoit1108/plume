<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Domain\SubscriptionStatus;

/**
 * Port du contexte Billing (V2.2). État d'abonnement par compte, filtré explicitement par tenant
 * (table hors RLS : écrite à l'inscription publique — sans tenant — et par les webhooks Stripe).
 *
 * Slice 1 : démarrage d'essai + droit d'accès (garde lecture seule).
 * Slice 2 : activation/transition pilotées par Stripe (checkout + webhooks).
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

    /**
     * Abonnement payant confirmé (checkout réussi) : passe en `active`, mémorise les identifiants
     * Stripe et la fin de période. Upsert (le tenant a déjà une ligne d'essai).
     */
    public function activate(string $tenantId, string $customerId, string $subscriptionId, ?\DateTimeImmutable $currentPeriodEnd): void;

    /** Transition pilotée par un webhook Stripe (renouvellement, impayé, résiliation), par client Stripe. */
    public function applyStatusByCustomer(string $customerId, SubscriptionStatus $status, ?\DateTimeImmutable $currentPeriodEnd): void;

    /** Identifiant client Stripe du tenant (pour ouvrir le portail), ou null s'il n'a jamais payé. */
    public function stripeCustomerFor(string $tenantId): ?string;
}
