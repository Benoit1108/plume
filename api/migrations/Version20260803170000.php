<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Durcissement Billing (revue santé, S-P2b/S-P2c) :
 *  - `stripe_webhook_event` : journal des `event.id` Stripe déjà traités → DÉDUPLICATION anti-rejeu
 *    (un payload signé rejoué dans la fenêtre de tolérance ne re-crédite plus l'accès). Table système
 *    hors tenant / hors RLS (comme `audit_log`), ajoutée au `schema_filter`.
 *  - `subscription.stripe_customer_id` : contrainte d'UNICITÉ (partielle, non nulle) — transforme
 *    l'invariant « 1 customer Stripe = 1 tenant » en garantie base (un webhook ne peut plus faire
 *    basculer plusieurs tenants).
 */
final class Version20260803170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing : journal de dédup des webhooks Stripe + unicité de stripe_customer_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stripe_webhook_event (event_id VARCHAR(255) NOT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(event_id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_subscription_stripe_customer ON subscription (stripe_customer_id) WHERE stripe_customer_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_subscription_stripe_customer');
        $this->addSql('DROP TABLE stripe_webhook_event');
    }
}
