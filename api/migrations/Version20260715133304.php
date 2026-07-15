<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Profil : ajout du nom/prénom d'affichage (page Compte, greeting « Bonjour {prénom} »).
 *
 * (Diff manuel : `doctrine:migrations:diff` inclut du bruit sur les tables de
 * projection non mappées à l'ORM — interaction, index partiels, types custom.
 * On ne conserve que les colonnes du profil.)
 */
final class Version20260715133304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profile: add first_name / last_name (display identity).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD last_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP first_name');
        $this->addSql('ALTER TABLE profile DROP last_name');
    }
}
