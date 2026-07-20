<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sourcing (M3.1a — ingestion RSS) : conservation du brut.
 *  - table `raw_alert` : contenu brut d'une annonce (audit / reprocessing), écrite en DBAL,
 *    non mappée ORM (soustraite au diff via schema_filter, patron `interaction`).
 *  - `candidate_lead.raw_ref` : référence vers le RawAlert d'origine (null pour la saisie manuelle).
 *
 * (Diff manuel : on ne conserve que les instructions voulues.)
 */
final class Version20260720113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sourcing (M3.1a): raw_alert table + candidate_lead.raw_ref.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE raw_alert (id VARCHAR NOT NULL, tenant_id UUID NOT NULL, source VARCHAR(32) NOT NULL, payload TEXT NOT NULL, fetched_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_raw_alert_tenant ON raw_alert (tenant_id)');
        $this->addSql('ALTER TABLE candidate_lead ADD raw_ref VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_lead DROP raw_ref');
        $this->addSql('DROP TABLE raw_alert');
    }
}
