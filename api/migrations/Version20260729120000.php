<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Perf (revue globale P1-1) : rend les policies RLS SARGABLES. L'ancienne forme
 * `(tenant_id)::text = current_setting(...)` cast la colonne uuid en text → l'index btree sur
 * `tenant_id` devient inutilisable (seq scan si une requête s'appuie sur la RLS seule — prouvé par
 * EXPLAIN : ×8-9 sur 1M lignes). Nouvelle forme : `tenant_id = NULLIF(current_setting('app.
 * current_tenant', true), '')::uuid` — comparaison uuid=uuid (index utilisable) TOUT en restant
 * FAIL-CLOSED : setting absent → NULL, setting vide (clear()) → NULLIF → NULL → `= NULL` → 0 ligne.
 *
 * + index partiel sur les relances en attente (le tick horaire scannait `lead` en entier).
 */
final class Version20260729120000 extends AbstractMigration
{
    private const array RLS_TABLES = [
        'alert_feed', 'candidate_lead', 'connected_mailbox', 'draft', 'interaction', 'lead',
        'notification', 'organization', 'outbound_message', 'profile', 'raw_alert', 'template',
    ];

    public function getDescription(): string
    {
        return 'Perf : policies RLS sargables (uuid=uuid) + index partiel relances dues.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::RLS_TABLES as $table) {
            $this->addSql(\sprintf('DROP POLICY tenant_isolation ON %s', $table));
            $this->addSql(\sprintf(<<<'SQL'
                CREATE POLICY tenant_isolation ON %s
                    USING (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid)
                    WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid)
                SQL, $table));
        }

        $this->addSql('CREATE INDEX idx_lead_due_followup ON lead (next_follow_up_at) WHERE next_follow_up_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_lead_due_followup');
        foreach (self::RLS_TABLES as $table) {
            $this->addSql(\sprintf('DROP POLICY tenant_isolation ON %s', $table));
            $this->addSql(\sprintf(<<<'SQL'
                CREATE POLICY tenant_isolation ON %s
                    USING ((tenant_id)::text = current_setting('app.current_tenant', true))
                    WITH CHECK ((tenant_id)::text = current_setting('app.current_tenant', true))
                SQL, $table));
        }
    }
}
