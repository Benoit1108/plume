<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vitrine — comptes de DÉMO éphémères : `app_user.demo_expires_at` (non nul ⇒ compte démo, purgé
 * après cette date par un tick réutilisant la purge RGPD). Colonne DB HORS mapping ORM (comme
 * `created_at`/`last_login_at`), écrite/lue en DBAL pur.
 */
final class Version20260803160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compte : app_user.demo_expires_at (comptes démo éphémères, hors mapping ORM).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD demo_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_app_user_demo_expires ON app_user (demo_expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_app_user_demo_expires');
        $this->addSql('ALTER TABLE app_user DROP demo_expires_at');
    }
}
