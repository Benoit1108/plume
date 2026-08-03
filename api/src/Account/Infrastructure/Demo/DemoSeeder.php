<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Demo;

use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Remplit un tenant de DÉMO avec des données FICTIVES crédibles (organisations, pistes à divers
 * stades, quelques interactions) pour que le produit soit « vivant » sans inscription. Écrit en SQL
 * direct SOUS le tenant démo (scope actif → RLS satisfaite). Valeurs d'enum en clair (UPPERCASE),
 * conformes au stockage réel.
 */
final class DemoSeeder
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
    ) {
    }

    public function seed(string $tenantId): void
    {
        $now = $this->clock->now();

        $orgs = [
            ['Éditions du Levant', 'PUBLISHER', 'PUBLISHING'],
            ['Studio Doublage Lumière', 'AV_STUDIO', 'AUDIOVISUAL'],
            ['Agence Mots & Cie', 'AGENCY', 'TECHNICAL'],
        ];
        $leads = [
            [0, 'TO_CONTACT', 'HIGH', 'DIRECT', null],
            [1, 'CONTACTED', 'MEDIUM', 'LINKEDIN', '-4 days'],
            [2, 'IN_DISCUSSION', 'HIGH', 'DIRECT', '-9 days'],
        ];

        $orgIds = [];
        foreach ($orgs as [$name, $type, $segment]) {
            $orgId = Uuid::v7()->toRfc4122();
            $orgIds[] = [$orgId, $segment];
            $this->connection->executeStatement(
                "INSERT INTO organization (id, tenant_id, name, type, working_languages, segments, do_not_contact, contacts)
                 VALUES (?, ?, ?, ?, '[\"en\",\"fr\"]', ?, false, '[]')",
                [$orgId, $tenantId, $name, $type, json_encode([$segment], \JSON_THROW_ON_ERROR)],
            );
        }

        foreach ($leads as [$orgIdx, $status, $priority, $source, $contactedAgo]) {
            [$orgId, $segment] = $orgIds[$orgIdx];
            $leadId = Uuid::v7()->toRfc4122();
            $this->connection->executeStatement(
                "INSERT INTO lead (id, tenant_id, organization_id, segment, status, language_pair, source, priority, created_at, follow_ups)
                 VALUES (?, ?, ?, ?, ?, 'en>fr', ?, ?, ?, '[]')",
                [$leadId, $tenantId, $orgId, $segment, $status, $source, $priority, $now->format('Y-m-d H:i:s')],
            );
            // Une piste contactée porte un acte au journal (le tableau de bord affiche de l'activité).
            if (null !== $contactedAgo) {
                $this->connection->executeStatement(
                    "INSERT INTO interaction (id, event_id, tenant_id, lead_id, type, payload, occurred_on)
                     VALUES (?, ?, ?, ?, 'contacted', '{}', ?)",
                    [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122(), $tenantId, $leadId, $now->modify($contactedAgo)->format('Y-m-d H:i:s')],
                );
            }
        }
    }
}
