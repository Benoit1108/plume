<?php

declare(strict_types=1);

namespace App\Billing\Application;

/**
 * ACL vers le fournisseur de paiement (Stripe). Le reste du code ne connaît que ce port : sans clés,
 * un adaptateur FACTICE prend le relais (dev/CI/E2E) — même patron que Gmail/Outlook.
 *
 * `plan` = `monthly` | `annual` (résolus en Price IDs Stripe côté adaptateur).
 */
interface BillingGateway
{
    /** Crée une session de paiement et renvoie l'URL vers laquelle rediriger la cliente. */
    public function createCheckoutSession(string $tenantId, string $email, string $plan): string;

    /** Crée une session du portail client (gérer/annuler l'abonnement) et renvoie son URL. */
    public function createPortalSession(string $customerId): string;
}
