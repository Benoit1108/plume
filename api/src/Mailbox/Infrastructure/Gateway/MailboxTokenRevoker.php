<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Gateway;

use App\Account\Application\Gateway\MailboxRevoker;
use App\Mailbox\Application\MailboxConnectorRegistry;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Shared\Domain\ValueObject\TenantId;
use Psr\Log\LoggerInterface;

/**
 * Adaptateur de {@see MailboxRevoker} (port du contexte Account) : révoque le consentement OAuth du
 * tenant AUPRÈS DU FOURNISSEUR. Réutilise la même mécanique que la révocation manuelle
 * (RevokeMailboxHandler) mais SANS toucher l'agrégat (la ligne `connected_mailbox` va être effacée
 * par la purge juste après) et SANS passer par le bus (on est déjà dans la transaction de la purge :
 * pas de commande imbriquée).
 *
 * Best-effort de bout en bout : boîte absente, token absent, indéchiffrable ou déjà mort côté
 * fournisseur → on n'empêche jamais la purge. On révoque à la purge (et non au soft-delete) car
 * c'est le seul point qui couvre uniformément les deux voies de suppression (self-service ET
 * support), déjà exécuté avec le bon tenant activé (RLS) ; c'est cohérent avec le modèle de délai
 * de grâce d'ADR-0025 (données ET consentement effacés au même moment, à l'expiration).
 */
final class MailboxTokenRevoker implements MailboxRevoker
{
    public function __construct(
        private readonly MailboxRepository $mailboxes,
        private readonly MailboxConnectorRegistry $connectors,
        private readonly TokenCipher $cipher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function revokeForTenant(string $tenantId): void
    {
        // Best-effort TOTAL : la révocation ne doit JAMAIS faire échouer la purge RGPD. Un token
        // indéchiffrable (clé changée), une ligne héritée mal formée ou une panne réseau côté
        // fournisseur sont tracés puis ignorés — l'effacement des données prime et continue.
        try {
            $mailbox = $this->mailboxes->findForTenant(TenantId::fromString($tenantId));
            $refresh = $mailbox?->refreshToken();
            if (null === $mailbox || null === $refresh) {
                return; // Aucune boîte connectée : rien à révoquer.
            }

            $plain = $this->cipher->decrypt($refresh->ciphertext());
            // Le connecteur est lui-même best-effort (un token déjà révoqué/expiré ne jette pas).
            $this->connectors->connectorFor($mailbox->provider()->value)->revoke($plain);
        } catch (\Throwable $e) {
            $this->logger->warning('Provider OAuth revocation skipped at purge.', ['tenant_id' => $tenantId, 'reason' => $e->getMessage()]);
        }
    }
}
