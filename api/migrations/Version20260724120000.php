<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RGPD — suppression de compte en soft-delete (V2.0-a).
 *
 * `app_user.deletion_requested_at` : horodatage de la demande de suppression. Non nul ⇒ le compte
 * est désactivé IMMÉDIATEMENT (UserChecker refuse l'auth, le scheduler cesse toute relève pour ce
 * tenant) ; la purge physique du tenant intervient après un délai de grâce (30 j) via un tick
 * planifié (V2.0-a2). Colonne sur `app_user` (hors RLS, lu avant le tenant) → 1 compte = 1 tenant.
 */
final class Version20260724120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RGPD : suppression de compte en soft-delete (app_user.deletion_requested_at).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD deletion_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN app_user.deletion_requested_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP deletion_requested_at');
    }
}
