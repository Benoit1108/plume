<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2.2 abonnement — table `subscription` (un par tenant). HORS RLS (écrite à l'inscription publique,
 * sans contexte tenant, et par les webhooks Stripe) : toujours filtrée par `tenant_id` explicite,
 * comme `app_user`. Hors ORM (DBAL pur, schema_filter). Colonnes Stripe prêtes pour la slice 2.
 */
final class Version20260803140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V2.2 : table subscription (état d\'abonnement par tenant, hors tenant/RLS).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscription (
            tenant_id UUID NOT NULL,
            status VARCHAR(16) NOT NULL,
            trial_ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            current_period_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            stripe_customer_id VARCHAR(64) DEFAULT NULL,
            stripe_subscription_id VARCHAR(64) DEFAULT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (tenant_id)
        )');
        $this->addSql('CREATE INDEX idx_subscription_stripe_customer ON subscription (stripe_customer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscription');
    }
}
