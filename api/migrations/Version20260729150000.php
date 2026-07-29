<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Métriques produit (back-office) : date de création du compte, pour les inscriptions dans le temps.
 * DEFAULT NOW() → les lignes existantes sont horodatées à la migration (approximation assumée pour
 * l'historique antérieur) et tout INSERT (ORM/CLI/inscription) la remplit sans code. Colonne HORS
 * mapping ORM `User` (lue seulement en SQL direct côté Admin) : invisible à schema:validate --skip-sync.
 */
final class Version20260729150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'app_user.created_at (métriques produit : inscriptions dans le temps).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP created_at');
    }
}
