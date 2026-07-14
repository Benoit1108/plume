<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** M1.4 — Rédaction assistée : tables draft + template, profil étendu (présentation). */
final class Version20260714090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1.4 : tables draft et template (contexte Drafting), colonnes bio/specialties/signature sur profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE draft (
            id VARCHAR(36) NOT NULL,
            tenant_id UUID NOT NULL,
            lead_id VARCHAR(255) NOT NULL,
            type VARCHAR(32) NOT NULL,
            target_language VARCHAR(2) NOT NULL,
            template_id VARCHAR(255) DEFAULT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            status VARCHAR(16) NOT NULL,
            failure_reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX idx_draft_tenant_lead ON draft (tenant_id, lead_id)');

        $this->addSql('CREATE TABLE template (
            id VARCHAR(36) NOT NULL,
            tenant_id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(32) NOT NULL,
            segment VARCHAR(32) NOT NULL,
            language VARCHAR(2) NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX idx_template_tenant ON template (tenant_id)');

        $this->addSql('ALTER TABLE profile ADD bio TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD specialties TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD signature TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP signature');
        $this->addSql('ALTER TABLE profile DROP specialties');
        $this->addSql('ALTER TABLE profile DROP bio');
        $this->addSql('DROP TABLE template');
        $this->addSql('DROP TABLE draft');
    }
}
