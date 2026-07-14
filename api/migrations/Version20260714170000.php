<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** M2.2 — envoi : table outbound_message (le fil thread_key servira à capter les réponses). */
final class Version20260714170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2.2 : table outbound_message (statut d\'envoi, thread_key par tenant+lead).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE outbound_message (
            id VARCHAR(36) NOT NULL,
            tenant_id UUID NOT NULL,
            lead_id VARCHAR(255) NOT NULL,
            draft_id VARCHAR(255) NOT NULL,
            draft_type VARCHAR(32) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            thread_key VARCHAR(255) DEFAULT NULL,
            status VARCHAR(16) NOT NULL,
            failure_reason VARCHAR(255) DEFAULT NULL,
            requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX idx_outbound_tenant_lead ON outbound_message (tenant_id, lead_id)');
        $this->addSql('CREATE INDEX idx_outbound_thread ON outbound_message (tenant_id, thread_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE outbound_message');
    }
}
