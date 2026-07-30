<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pipeline personnalisable (V2.3, ADR-0031) : libellés d'étapes par tenant, stockés en JSON sur le
 * profil (map statut → libellé, overrides). DEFAULT '{}' → lignes existantes = aucun override (libellés
 * i18n par défaut). Purement cosmétique : la machine à états ne change pas.
 */
final class Version20260730120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil : libellés d\'étapes du pipeline personnalisés (pipeline_labels JSON).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE profile ADD pipeline_labels JSON DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP pipeline_labels');
    }
}
