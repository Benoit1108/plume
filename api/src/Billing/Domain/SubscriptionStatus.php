<?php

declare(strict_types=1);

namespace App\Billing\Domain;

/**
 * État d'abonnement d'un compte (V2.2). L'ACCÈS EN ÉCRITURE au produit en dépend : un essai en cours
 * ou un abonnement actif/offert donne l'accès complet ; un essai expiré, un impayé ou une résiliation
 * bascule le compte en LECTURE SEULE (données conservées, ré-abonnement possible).
 *
 * `trialing` / `active` / `canceled` sont pilotés par Stripe (webhooks, V2.2 slice 2) ; `comped` est
 * un accès offert posé depuis le back-office (hors facturation).
 */
enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case COMPED = 'comped';

    /**
     * Le statut donne-t-il l'accès en écriture ? Pour un essai, seulement s'il n'est pas expiré
     * (`$trialStillValid`). Logique PURE — la date/horloge est résolue par l'appelant.
     */
    public function grantsWriteAccess(bool $trialStillValid): bool
    {
        return match ($this) {
            self::ACTIVE, self::COMPED => true,
            self::TRIALING => $trialStillValid,
            self::PAST_DUE, self::CANCELED => false,
        };
    }
}
