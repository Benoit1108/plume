<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Préférences fines de notification (V2.3) : map JSON `type → {inApp, email}` par tenant. Ne stocke
 * que les COUPURES (défaut = tout activé) — filtrées par prédicat JSONB à la lecture (cloche/digest).
 */
final class Version20260730180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Profil : préférences fines de notification (notification_preferences JSON, défaut {}).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE profile ADD notification_preferences JSON DEFAULT '{}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE profile DROP notification_preferences');
    }
}
