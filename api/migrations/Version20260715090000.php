<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Remédiation revue fin M2 : un seul envoi non-FAILED par brouillon (anti double envoi). */
final class Version20260715090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index partiel unique (tenant_id, draft_id) sur outbound_message hors FAILED.';
    }

    public function up(Schema $schema): void
    {
        // Partiel : un brouillon dont l'envoi a échoué peut être renvoyé.
        $this->addSql("CREATE UNIQUE INDEX uniq_outbound_active_draft ON outbound_message (tenant_id, draft_id) WHERE status <> 'FAILED'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_outbound_active_draft');
    }
}
