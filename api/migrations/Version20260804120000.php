<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2.4 — réactivation des clients dormants : seuil par tenant (jours sans interaction sur un client
 * GAGNÉ avant proposition de réactivation). Défaut 120 j ; 0 = désactivé. Colonne mappée à l'ORM.
 */
final class Version20260804120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil : dormant_client_threshold_days (réactivation des clients dormants, V2.4).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile ADD dormant_client_threshold_days INT NOT NULL DEFAULT 120');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP dormant_client_threshold_days');
    }
}
