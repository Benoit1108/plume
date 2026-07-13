<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** M1.3 — profil (objectif hebdomadaire + fuseau), un par tenant. */
final class Version20260713211000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1.3 : table profile (weekly_goal, timezone), clé = tenant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profile (
            tenant_id UUID NOT NULL,
            weekly_goal INT NOT NULL,
            timezone VARCHAR(64) NOT NULL,
            PRIMARY KEY (tenant_id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE profile');
    }
}
