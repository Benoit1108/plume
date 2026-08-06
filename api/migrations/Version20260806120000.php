<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lot « densité » : rendre les sessions actives IDENTIFIABLES sur la page Compte (appareil +
 * dernière activité). Nullable : les sessions ouvertes avant cette migration n'ont ni l'un ni
 * l'autre — le front affiche alors un repli. Table hors tenant (auth), donc hors RLS.
 */
final class Version20260806120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sessions : refresh_tokens.user_agent + last_seen_at (appareil et activité).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens ADD user_agent VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens DROP user_agent');
        $this->addSql('ALTER TABLE refresh_tokens DROP last_seen_at');
    }
}
