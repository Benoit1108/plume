<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Préférence de digest email des notifications sur le profil (NONE/DAILY/WEEKLY). DEFAULT 'DAILY' en
 * base : les lignes existantes sont remplies automatiquement, et un INSERT brut qui l'omet (seed,
 * tests) reste valide. Le DEFAULT n'affecte pas `doctrine:schema:validate` (CI en `--skip-sync`) et
 * l'agrégat fournit toujours la valeur à l'écriture ORM.
 */
final class Version20260729140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil : préférence de fréquence du digest email (digest_frequency).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE profile ADD digest_frequency VARCHAR(10) DEFAULT 'DAILY' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP digest_frequency');
    }
}
