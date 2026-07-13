<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Revue de santé M1.1 — couche données :
 * - tenant_id en UUID partout (cohérence avec app_user) + index (toutes les requêtes filtrent dessus) ;
 * - unicité du nom d'organisation par tenant, insensible à la casse (décision D1) ;
 * - colonnes JSON → JSONB (indexables, comparables — natif PG 17).
 */
final class Version20260713170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index tenant, unicité du nom par tenant, JSONB, tenant_id en UUID.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ALTER COLUMN tenant_id TYPE UUID USING tenant_id::uuid');
        $this->addSql('ALTER TABLE lead ALTER COLUMN tenant_id TYPE UUID USING tenant_id::uuid');

        $this->addSql('CREATE INDEX idx_organization_tenant ON organization (tenant_id)');
        $this->addSql('CREATE INDEX idx_lead_tenant ON lead (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_tenant_lower_name ON organization (tenant_id, LOWER(name))');

        $this->addSql('ALTER TABLE organization ALTER COLUMN working_languages TYPE JSONB USING working_languages::jsonb');
        $this->addSql('ALTER TABLE organization ALTER COLUMN segments TYPE JSONB USING segments::jsonb');
        $this->addSql('ALTER TABLE organization ALTER COLUMN contacts TYPE JSONB USING contacts::jsonb');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization ALTER COLUMN contacts TYPE JSON USING contacts::json');
        $this->addSql('ALTER TABLE organization ALTER COLUMN segments TYPE JSON USING segments::json');
        $this->addSql('ALTER TABLE organization ALTER COLUMN working_languages TYPE JSON USING working_languages::json');

        $this->addSql('DROP INDEX uniq_organization_tenant_lower_name');
        $this->addSql('DROP INDEX idx_lead_tenant');
        $this->addSql('DROP INDEX idx_organization_tenant');

        $this->addSql('ALTER TABLE lead ALTER COLUMN tenant_id TYPE VARCHAR USING tenant_id::text');
        $this->addSql('ALTER TABLE organization ALTER COLUMN tenant_id TYPE VARCHAR USING tenant_id::text');
    }
}
