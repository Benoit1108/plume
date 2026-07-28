<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2.1a — mot de passe oublié : table des jetons de réinitialisation. Ne stocke que le HASH du jeton
 * (le clair n'existe que dans l'email), l'email cible et une expiration courte. Hors RLS (utilisée
 * avant le tenant, comme `app_user`/`refresh_tokens`) → le rôle runtime plume_app y accède via les
 * DEFAULT PRIVILEGES (app:db:provision-app-role).
 */
final class Version20260728120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V2.1a : table password_reset_token (mot de passe oublié).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_token (
            id UUID NOT NULL,
            email VARCHAR(180) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_password_reset_token_hash ON password_reset_token (token_hash)');
        $this->addSql('CREATE INDEX idx_password_reset_token_email ON password_reset_token (email)');
        $this->addSql('COMMENT ON COLUMN password_reset_token.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_token.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_token');
    }
}
