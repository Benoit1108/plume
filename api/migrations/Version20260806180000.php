<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Idempotence des emails périodiques (revue BACK-P2a) : registre des envois déjà faits, pour qu'un
 * rejeu Messenger ou une occurrence rejouée par le scheduler ne réexpédie pas bilans et digests.
 *
 * Hors tenant et hors RLS, ASSUMÉ : état de MAINTENANCE globale écrit par le scheduler propriétaire
 * (comme la purge des notifications), sans donnée métier. La clé est opaque (tenant + période) et
 * ne contient aucune donnée personnelle — pas d'email, pas de contenu.
 *
 * `index_username` : au passage, l'index manquant sur `refresh_tokens.username` (revue PERF-P2a) —
 * chaque connexion ET chaque rafraîchissement y filtrent, désormais trois fois (purge des sessions).
 */
final class Version20260806180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registre d\'idempotence des emails périodiques + index refresh_tokens.username.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE email_dispatch (
                id BIGSERIAL PRIMARY KEY,
                dispatch_key VARCHAR(255) NOT NULL,
                sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_email_dispatch_key ON email_dispatch (dispatch_key)');
        // Rétention : la purge quotidienne s'appuie dessus.
        $this->addSql('CREATE INDEX idx_email_dispatch_sent_at ON email_dispatch (sent_at)');

        $this->addSql('CREATE INDEX idx_refresh_tokens_username ON refresh_tokens (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_refresh_tokens_username');
        $this->addSql('DROP TABLE email_dispatch');
    }
}
