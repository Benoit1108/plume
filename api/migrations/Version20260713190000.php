<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1.2 — pipeline Lead :
 * - enrichissement de `lead` (contact, paire de langues, source, priorité, pause, horodatages)
 *   + index et unicité « une piste active par organisation » (index partiel) ;
 * - table `interaction` (journal append-only projeté depuis les domain events,
 *   idempotent par event_id) ;
 * - purge des tables transitoires de dev : anciennes pistes du squelette M0 et
 *   events sérialisés dans l'ancien format (aucune donnée de prod n'existe).
 */
final class Version20260713190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1.2 : colonnes lead (pipeline complet), table interaction, index et unicité active.';
    }

    public function up(Schema $schema): void
    {
        // Données transitoires de dev (squelette M0 + events à l'ancien format).
        // messenger_messages est créée au runtime par le transport doctrine :
        // absente sur une base vierge (CI), d'où la garde.
        $this->addSql('DELETE FROM lead');
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF to_regclass('public.messenger_messages') IS NOT NULL THEN
                    DELETE FROM messenger_messages;
                END IF;
            END $$;
            SQL);

        $this->addSql('ALTER TABLE lead ADD contact_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE lead ADD language_pair VARCHAR(5) NOT NULL');
        $this->addSql('ALTER TABLE lead ADD source VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE lead ADD priority VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE lead ADD status_before_pause VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE lead ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE lead ADD last_contacted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE lead ADD last_reply_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_lead_tenant_status ON lead (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_lead_tenant_organization ON lead (tenant_id, organization_id)');
        // Décision M1.2 n°1 : une seule piste NON terminale par organisation et par tenant.
        $this->addSql("CREATE UNIQUE INDEX uniq_lead_active_per_organization ON lead (tenant_id, organization_id) WHERE status NOT IN ('WON', 'LOST')");

        $this->addSql('CREATE TABLE interaction (
            id VARCHAR(36) NOT NULL,
            event_id VARCHAR(36) NOT NULL,
            tenant_id UUID NOT NULL,
            lead_id VARCHAR(255) NOT NULL,
            type VARCHAR(30) NOT NULL,
            payload JSONB NOT NULL,
            occurred_on TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_interaction_event ON interaction (event_id)');
        $this->addSql('CREATE INDEX idx_interaction_tenant_lead ON interaction (tenant_id, lead_id, occurred_on)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE interaction');

        $this->addSql('DROP INDEX uniq_lead_active_per_organization');
        $this->addSql('DROP INDEX idx_lead_tenant_organization');
        $this->addSql('DROP INDEX idx_lead_tenant_status');

        $this->addSql('ALTER TABLE lead DROP last_reply_at');
        $this->addSql('ALTER TABLE lead DROP last_contacted_at');
        $this->addSql('ALTER TABLE lead DROP created_at');
        $this->addSql('ALTER TABLE lead DROP status_before_pause');
        $this->addSql('ALTER TABLE lead DROP priority');
        $this->addSql('ALTER TABLE lead DROP source');
        $this->addSql('ALTER TABLE lead DROP language_pair');
        $this->addSql('ALTER TABLE lead DROP contact_id');
    }
}
