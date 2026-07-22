<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multi-tenant : Row-Level Security (filet de sécurité en base, chantier 1b-4).
 *
 * Active la RLS + une policy d'isolation sur toutes les tables métier portant `tenant_id`.
 * La policy compare `tenant_id` à la variable de session `app.current_tenant` (posée par
 * TenantScope en HTTP et côté worker) : hors tenant (variable NULL/vide), aucune ligne n'est
 * visible → FAIL-CLOSED, symétrique du filtre applicatif Doctrine.
 *
 * ENABLE (et non FORCE) : le rôle PROPRIÉTAIRE `plume` contourne la RLS — migrations, tests et
 * scheduler (maintenance cross-tenant) restent pleinement fonctionnels. Seul le rôle RUNTIME
 * `plume_app` (API + worker, non-propriétaire) y est soumis.
 *
 * EXCLUS : `app_user` (lu AVANT le tenant, au login → jamais de RLS), `refresh_tokens`,
 * `messenger_messages`, `doctrine_migration_versions` (infra, sans tenant_id).
 */
final class Version20260722120000 extends AbstractMigration
{
    private const array TABLES = [
        'alert_feed',
        'candidate_lead',
        'connected_mailbox',
        'draft',
        'interaction',
        'lead',
        'organization',
        'outbound_message',
        'profile',
        'raw_alert',
        'template',
    ];

    public function getDescription(): string
    {
        return 'Multi-tenant: enable Row-Level Security + tenant isolation policy on business tables.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
            // USING = lignes visibles en lecture/écriture ; WITH CHECK = lignes qu'on autorise à
            // insérer/modifier — toutes deux liées au tenant courant. `true` (missing_ok) : hors
            // session tenantée, current_setting renvoie NULL → prédicat faux → fail-closed.
            $this->addSql(\sprintf(
                "CREATE POLICY tenant_isolation ON %s USING (tenant_id::text = current_setting('app.current_tenant', true)) WITH CHECK (tenant_id::text = current_setting('app.current_tenant', true))",
                $table,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation ON %s', $table));
            $this->addSql(\sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table));
        }
    }
}
