<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal d'AUDIT hors tenant (V2 back-office + backlog ADR-0025) : trace les actions sensibles —
 * demande de suppression de compte, purge effectuée, actions du back-office. Volontairement SANS
 * colonne `tenant_id` (la cible est dans `target`) : ce journal survit à la purge RGPD du tenant
 * (c'est son but — prouver que la suppression a eu lieu) et ne porte pas de PII au-delà de
 * l'identifiant technique. Hors ORM (DBAL pur, schema_filter) et hors RLS.
 */
final class Version20260728150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V2 back-office : table audit_log (journal d\'audit hors tenant).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_log (
            id UUID NOT NULL,
            actor VARCHAR(180) NOT NULL,
            action VARCHAR(60) NOT NULL,
            target VARCHAR(180) NOT NULL,
            details JSONB NOT NULL,
            occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX idx_audit_log_target ON audit_log (target)');
        $this->addSql('CREATE INDEX idx_audit_log_occurred ON audit_log (occurred_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
    }
}
