<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Garde-fou de coût IA : compteur de consommation GLOBAL (hors tenant / hors RLS, comme audit_log,
 * hors ORM via schema_filter). Agrégat mensuel des jetons Anthropic + nombre d'appels, alimenté par
 * l'adaptateur Claude et lu pour le plafond mensuel + le back-office.
 */
final class Version20260803100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Garde-fou coût IA : table ai_usage (compteur mensuel de jetons, hors tenant).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_usage (
            period VARCHAR(7) NOT NULL,
            input_tokens BIGINT NOT NULL DEFAULT 0,
            output_tokens BIGINT NOT NULL DEFAULT 0,
            calls INTEGER NOT NULL DEFAULT 0,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (period)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_usage');
    }
}
