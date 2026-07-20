<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sourcing (M3.1b) : table `alert_feed` — flux d'annonces (RSS) configurés par tenant.
 *
 * (Diff manuel : on ne conserve que les instructions voulues.)
 */
final class Version20260720140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sourcing (M3.1b): alert_feed table (per-tenant RSS feeds).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE alert_feed (id VARCHAR NOT NULL, tenant_id UUID NOT NULL, source VARCHAR(32) NOT NULL, url TEXT NOT NULL, label VARCHAR(120) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_alert_feed_tenant_active ON alert_feed (tenant_id, active)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE alert_feed');
    }
}
