<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sourcing (M3) : table candidate_lead (file de tri des annonces ingérées).
 *
 * (Diff manuel : `doctrine:migrations:diff` inclut du bruit sur les tables de
 * projection non mappées à l'ORM — interaction, index partiels, types custom.
 * On ne conserve que la table candidate_lead.)
 */
final class Version20260719103743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sourcing: candidate_lead table (triage queue).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE candidate_lead (id VARCHAR NOT NULL, tenant_id UUID NOT NULL, source VARCHAR(32) NOT NULL, dedup_hash VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, title VARCHAR(300) NOT NULL, organization_name VARCHAR(200) DEFAULT NULL, language_pair VARCHAR(16) DEFAULT NULL, url TEXT DEFAULT NULL, excerpt TEXT DEFAULT NULL, posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, promoted_lead_id VARCHAR(255) DEFAULT NULL, organization_id VARCHAR(255) DEFAULT NULL, ingested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_candidate_tenant_status ON candidate_lead (tenant_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_candidate_dedup ON candidate_lead (tenant_id, dedup_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE candidate_lead');
    }
}
