<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1.3 — relances : collection follow_ups (JSONB) dans l'agrégat Lead +
 * dénormalisation next_follow_up_at/label (requête « dues aujourd'hui ») + index.
 */
final class Version20260713210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1.3 : relances dans lead (follow_ups JSONB, next_follow_up_at dénormalisé + index).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE lead ADD follow_ups JSONB DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE lead ALTER COLUMN follow_ups DROP DEFAULT');
        $this->addSql('ALTER TABLE lead ADD next_follow_up_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE lead ADD next_follow_up_label VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_lead_tenant_next_follow_up ON lead (tenant_id, next_follow_up_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_lead_tenant_next_follow_up');
        $this->addSql('ALTER TABLE lead DROP next_follow_up_label');
        $this->addSql('ALTER TABLE lead DROP next_follow_up_at');
        $this->addSql('ALTER TABLE lead DROP follow_ups');
    }
}
