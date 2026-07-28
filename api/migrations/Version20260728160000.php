<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * 2FA/TOTP (V2, slice sécurité) sur `app_user` (lu au login, avant le tenant — hors RLS) :
 * - totp_secret          : secret Base32 quand la 2FA est ACTIVE (null sinon).
 * - totp_pending_secret  : secret en cours d'enrôlement (posé au setup, promu au confirm).
 * - totp_last_used_step  : ANTI-REJEU — dernier pas de temps TOTP accepté ; un code déjà utilisé
 *                          est refusé même dans sa fenêtre de validité (vol par-dessus l'épaule).
 * - backup_codes         : codes de secours HASHÉS (sha256), consommés à l'usage.
 */
final class Version20260728160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V2 sécurité : 2FA TOTP (secret, enrôlement, anti-rejeu, codes de secours).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD totp_secret VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD totp_pending_secret VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD totp_last_used_step BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD backup_codes JSONB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP totp_secret');
        $this->addSql('ALTER TABLE app_user DROP totp_pending_secret');
        $this->addSql('ALTER TABLE app_user DROP totp_last_used_step');
        $this->addSql('ALTER TABLE app_user DROP backup_codes');
    }
}
