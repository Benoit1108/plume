<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Séquences de relance configurables (V2.3) : la cadence par tenant, stockée en JSON sur le profil
 * (délais en jours entre étapes). DEFAULT '[7, 21, 45]' → lignes existantes = cadence historique,
 * INSERT ORM rempli par l'agrégat. Colonne mappée `type="json"` côté ORM (schema:validate OK).
 */
final class Version20260730100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil : séquence de relance configurable (follow_up_cadence JSON).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE profile ADD follow_up_cadence JSON DEFAULT '[7, 21, 45]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP follow_up_cadence');
    }
}
