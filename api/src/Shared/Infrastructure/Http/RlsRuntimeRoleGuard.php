<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Garde-fou de déploiement : en PRODUCTION, refuse de servir si la connexion de l'API contourne la
 * Row-Level Security (ADR-0023).
 *
 * POURQUOI : la RLS est la SECONDE ligne de défense multi-tenant, et son mode d'échec est
 * SILENCIEUX. Un rôle `SUPERUSER` ou `BYPASSRLS` (typiquement le propriétaire `plume`, qui est le
 * `POSTGRES_USER` du conteneur) voit toutes les lignes de tous les tenants sans qu'aucune requête
 * n'échoue : l'application fonctionne parfaitement, simplement sans filet. Une erreur de câblage
 * (`.env.runtime.local` oublié, cf. compose.prod.yaml) passerait donc totalement inaperçue.
 * On préfère un démarrage refusé, explicite, à une isolation absente et invisible.
 *
 * Ne concerne QUE le trafic HTTP tenanté. Le scheduler, les migrations et la console tournent
 * légitimement en propriétaire (maintenance cross-tenant) et ne passent pas par kernel.request.
 *
 * Enregistré uniquement dans `when@prod` (cf. services.yaml), vérifié une seule fois par processus.
 */
final class RlsRuntimeRoleGuard
{
    private bool $verified = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ($this->verified || !$event->isMainRequest()) {
            return;
        }

        try {
            /** @var array{rolname: string, privileged: bool}|false $role */
            $role = $this->connection->fetchAssociative(
                'SELECT rolname, (rolsuper OR rolbypassrls) AS privileged FROM pg_roles WHERE rolname = current_user',
            );
        } catch (\Throwable $e) {
            // Base injoignable : c'est une panne, pas une faille de configuration. On ne transforme
            // pas une indisponibilité en erreur cryptique — la requête échouera de toute façon.
            $this->logger->warning('Impossible de vérifier le rôle base du runtime (RLS).', ['exception' => $e]);

            return;
        }

        if (false !== $role && (bool) $role['privileged']) {
            throw new \RuntimeException(\sprintf('Production misconfiguration: the API runs as "%s", a SUPERUSER/BYPASSRLS role, so Row-Level Security is inert (ADR-0023). Point DATABASE_URL at the non-owner runtime role (see api/.env.runtime.example and compose.prod.yaml).', $role['rolname']));
        }

        $this->verified = true;
    }
}
