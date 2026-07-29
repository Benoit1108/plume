<?php

declare(strict_types=1);

namespace App\Account\Application\Gateway;

/**
 * Frontière Account → Passerelle email : à la purge RGPD d'un compte, il faut RÉVOQUER le
 * consentement OAuth CÔTÉ FOURNISSEUR (Google/Microsoft) — pas seulement détruire les tokens
 * chiffrés en base (trou tracé à l'ADR-0025). Le port est défini ici (le consommateur exprime
 * son besoin en termes primitifs) ; l'adaptateur vit dans le contexte Mailbox, qui seul sait
 * déchiffrer le token et parler au bon connecteur. Best-effort par contrat : ne jette jamais
 * (une boîte absente, un token déjà mort ou indéchiffrable ne doit pas faire échouer la purge).
 */
interface MailboxRevoker
{
    public function revokeForTenant(string $tenantId): void;
}
