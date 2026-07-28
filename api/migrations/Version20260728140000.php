<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Centre de notifications (V2, contexte Notification) : projection append-only sur les domain
 * events (comme `interaction`) + notifications planifiées (relances dues). Hors ORM (DBAL pur,
 * ajoutée au schema_filter). `event_id` unique = idempotence des projections (retries Messenger)
 * ET des ticks (identifiant déterministe `followup_due:<lead>:<date>`).
 *
 * Table tenantée → RLS + policy OBLIGATOIRES (garde-fou RlsCoverageTest).
 */
final class Version20260728140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V2 : table notification (centre de notifications) + RLS.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (
            id UUID NOT NULL,
            event_id VARCHAR(120) NOT NULL,
            tenant_id UUID NOT NULL,
            type VARCHAR(40) NOT NULL,
            payload JSONB NOT NULL,
            read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            occurred_on TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_notification_event ON notification (event_id)');
        $this->addSql('CREATE INDEX idx_notification_tenant_occurred ON notification (tenant_id, occurred_on DESC)');
        $this->addSql('CREATE INDEX idx_notification_tenant_unread ON notification (tenant_id) WHERE read_at IS NULL');

        // Filet base multi-tenant, symétrique des autres tables métier (ADR-0023).
        $this->addSql('ALTER TABLE notification ENABLE ROW LEVEL SECURITY');
        $this->addSql(<<<'SQL'
            CREATE POLICY tenant_isolation ON notification
                USING (tenant_id::text = current_setting('app.current_tenant', true))
                WITH CHECK (tenant_id::text = current_setting('app.current_tenant', true))
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification');
    }
}
