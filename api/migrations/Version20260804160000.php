<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2.4 — bilan hebdomadaire par email : préférence par tenant (opt-out). Défaut activé. Colonne ORM.
 */
final class Version20260804160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil : weekly_report_enabled (bilan hebdomadaire par email, V2.4).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD weekly_report_enabled BOOLEAN NOT NULL DEFAULT true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP weekly_report_enabled');
    }
}
