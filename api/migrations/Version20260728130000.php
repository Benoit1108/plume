<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2.1b — inscription publique : vérification d'email. `app_user.email_verified` par défaut `true`
 * (les comptes existants + ceux créés par la CLI/le seed sont de confiance) ; seule l'inscription
 * en libre-service crée un compte non vérifié, à qui l'auth est refusée tant que l'email n'est pas
 * confirmé. Colonne sur `app_user` (hors RLS).
 */
final class Version20260728130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V2.1b : app_user.email_verified (vérification d\'email à l\'inscription).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD email_verified BOOLEAN NOT NULL DEFAULT true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP email_verified');
    }
}
