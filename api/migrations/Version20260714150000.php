<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** M2.1 — Passerelle email : table connected_mailbox (tokens chiffrés, ADR-0016). */
final class Version20260714150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2.1 : table connected_mailbox (une par tenant en V1 — index unique levable).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE connected_mailbox (
            id VARCHAR(36) NOT NULL,
            tenant_id UUID NOT NULL,
            provider VARCHAR(16) NOT NULL,
            email_address VARCHAR(255) NOT NULL,
            access_token TEXT DEFAULT NULL,
            refresh_token TEXT DEFAULT NULL,
            status VARCHAR(16) NOT NULL,
            failure_reason VARCHAR(255) DEFAULT NULL,
            connected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            sync_cursor VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_mailbox_tenant ON connected_mailbox (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE connected_mailbox');
    }
}
