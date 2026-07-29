<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Infrastructure\Mail\AccountMailer;
use App\Notification\Infrastructure\Scheduler\SendNotificationDigestsHandler;
use App\Notification\Infrastructure\Scheduler\SendNotificationDigestsTick;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/**
 * Digest email des notifications : sélection par préférence (DAILY/WEEKLY/NONE), fenêtre glissante
 * calée sur la fréquence, comptes en suppression / non vérifiés exclus, notifications lues ou hors
 * fenêtre ignorées, aucun email si rien à résumer. Horloge FIXÉE un LUNDI pour couvrir DAILY ET WEEKLY.
 */
final class SendNotificationDigestsTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private const MONDAY = '2026-08-03 08:00:00';

    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE notification, profile, app_user RESTART IDENTITY CASCADE');
    }

    public function testSendsDigestsOnlyToDueOptedInAccountsWithFreshUnread(): void
    {
        // A : DAILY, notif non lue il y a 6 h → digest.
        $this->seed('a@plume.test', 'DAILY', occurredOn: '2026-08-03 02:00:00');
        // B : DAILY mais notif d'il y a 2 j (hors fenêtre 24 h) → rien.
        $this->seed('b@plume.test', 'DAILY', occurredOn: '2026-08-01 09:00:00');
        // C : WEEKLY, notif d'il y a 3 j (dans la fenêtre 7 j) — on est lundi → digest.
        $this->seed('c@plume.test', 'WEEKLY', occurredOn: '2026-07-31 09:00:00');
        // D : NONE → jamais de digest.
        $this->seed('d@plume.test', 'NONE', occurredOn: self::MONDAY);
        // E : DAILY mais compte en suppression → exclu.
        $this->seed('e@plume.test', 'DAILY', occurredOn: self::MONDAY, deletionRequested: true);
        // F : DAILY mais email non vérifié → exclu.
        $this->seed('f@plume.test', 'DAILY', occurredOn: self::MONDAY, emailVerified: false);
        // G : DAILY mais notif déjà LUE → rien à résumer.
        $this->seed('g@plume.test', 'DAILY', occurredOn: self::MONDAY, read: true);

        $mailer = static::getContainer()->get(AccountMailer::class);
        \assert($mailer instanceof AccountMailer);
        $handler = new SendNotificationDigestsHandler($this->connection, $mailer, new FixedClock(new \DateTimeImmutable(self::MONDAY)));
        ($handler)(new SendNotificationDigestsTick());

        // On assère l'ENSEMBLE des destinataires (chaque envoi émet 2 MessageEvent — queued + sent —
        // via le transport messenger sync, d'où le dédoublonnage). Seuls A (DAILY) et C (WEEKLY, lundi)
        // reçoivent ; B/D/E/F/G sont exclus.
        $recipients = [];
        foreach (self::getMailerMessages() as $message) {
            self::assertInstanceOf(Email::class, $message);
            $recipients[] = $message->getTo()[0]->getAddress();
        }
        $recipients = array_values(array_unique($recipients));
        sort($recipients);
        self::assertSame(['a@plume.test', 'c@plume.test'], $recipients);
    }

    private function seed(
        string $email,
        string $frequency,
        string $occurredOn,
        bool $deletionRequested = false,
        bool $emailVerified = true,
        bool $read = false,
    ): void {
        $tenant = Uuid::v7()->toRfc4122();
        $this->connection->executeStatement(
            'INSERT INTO app_user (id, tenant_id, email, password, roles, email_verified, deletion_requested_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Uuid::v7()->toRfc4122(), $tenant, $email, 'x', '[]', $emailVerified ? 'true' : 'false', $deletionRequested ? self::MONDAY : null],
        );
        $this->connection->executeStatement(
            "INSERT INTO profile (tenant_id, weekly_goal, timezone, digest_frequency) VALUES (?, 5, 'Europe/Paris', ?)",
            [$tenant, $frequency],
        );
        $this->connection->executeStatement(
            'INSERT INTO notification (id, event_id, tenant_id, type, payload, occurred_on, read_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Uuid::v7()->toRfc4122(), 'evt-'.$tenant, $tenant, 'reply_received', '{}', $occurredOn, $read ? $occurredOn : null],
        );
    }
}
