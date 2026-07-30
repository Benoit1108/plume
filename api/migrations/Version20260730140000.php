<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Valeur estimée du deal sur la piste (V2.3) : montant en euros, nullable (non estimé par défaut).
 * Alimente le tableau de bord (valeur du pipeline / gagnée).
 */
final class Version20260730140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Piste : valeur estimée du deal (estimated_value, euros, nullable).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lead ADD estimated_value INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lead DROP estimated_value');
    }
}
