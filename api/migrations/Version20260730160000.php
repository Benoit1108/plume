<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Chiffrement du secret TOTP au repos (ADR-0027 amendé) : les colonnes passent de 128 à 255
 * caractères pour accueillir le ciphertext (nonce + secret + MAC, base64) au lieu du secret brut.
 * Aucune donnée à migrer (2FA non déployée en prod ; les secrets de dev/test sont re-enrôlés).
 */
final class Version20260730160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Compte : élargit totp_secret / totp_pending_secret (128 → 255) pour le secret chiffré au repos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ALTER COLUMN totp_secret TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE app_user ALTER COLUMN totp_pending_secret TYPE VARCHAR(255)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ALTER COLUMN totp_secret TYPE VARCHAR(128)');
        $this->addSql('ALTER TABLE app_user ALTER COLUMN totp_pending_secret TYPE VARCHAR(128)');
    }
}
