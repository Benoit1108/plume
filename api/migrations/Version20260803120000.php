<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Back-office — fiche compte : `app_user.last_login_at`, posé à chaque login réel. Colonne DB HORS
 * mapping ORM (comme `created_at`) : renseignée/lue en DBAL pur, tolérée par schema-validate --skip-sync.
 */
final class Version20260803120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compte : app_user.last_login_at (dernière connexion, hors mapping ORM).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP last_login_at');
    }
}
