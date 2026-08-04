<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Infrastructure\Mail\AccountMailer;
use App\Notification\Infrastructure\Scheduler\SendWeeklyReportsHandler;
use App\Notification\Infrastructure\Scheduler\SendWeeklyReportsTick;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/**
 * Bilan hebdomadaire par email (V2.4) : envoyé le LUNDI aux comptes opt-in avec de l'activité sur la
 * fenêtre 7 j ; rien un autre jour, rien pour une semaine silencieuse, ni pour les comptes coupés /
 * en suppression / non vérifiés. Chiffres agrégés (jamais de contenu de piste).
 */
final class SendWeeklyReportsTest extends KernelTestCase
{
    use MailerAssertionsTrait;

    private const MONDAY = '2026-08-03 08:00:00';
    private const TUESDAY = '2026-08-04 08:00:00';

    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE interaction, profile, app_user RESTART IDENTITY CASCADE');
    }

    public function testSendsMondayReportsOnlyToActiveOptedInAccounts(): void
    {
        // A : opt-in, démarche cette semaine → bilan.
        $this->seed('a@plume.test', activityAt: '2026-07-31 09:00:00');
        // B : opt-in mais dernière activité il y a 10 j (hors fenêtre 7 j) → rien.
        $this->seed('b@plume.test', activityAt: '2026-07-24 09:00:00');
        // C : opt-OUT (weekly_report_enabled=false) → rien.
        $this->seed('c@plume.test', activityAt: '2026-07-31 09:00:00', enabled: false);
        // D : en suppression → exclu.
        $this->seed('d@plume.test', activityAt: '2026-07-31 09:00:00', deletionRequested: true);
        // E : email non vérifié → exclu.
        $this->seed('e@plume.test', activityAt: '2026-07-31 09:00:00', emailVerified: false);

        $handler = new SendWeeklyReportsHandler($this->connection, $this->mailer(), new FixedClock(new \DateTimeImmutable(self::MONDAY)));
        ($handler)(new SendWeeklyReportsTick());

        $recipients = [];
        foreach (self::getMailerMessages() as $message) {
            self::assertInstanceOf(Email::class, $message);
            $recipients[] = $message->getTo()[0]->getAddress();
        }
        $recipients = array_values(array_unique($recipients));
        self::assertSame(['a@plume.test'], $recipients);
    }

    public function testSendsNothingOutsideMonday(): void
    {
        $this->seed('a@plume.test', activityAt: '2026-08-03 09:00:00');

        $handler = new SendWeeklyReportsHandler($this->connection, $this->mailer(), new FixedClock(new \DateTimeImmutable(self::TUESDAY)));
        ($handler)(new SendWeeklyReportsTick());

        self::assertCount(0, self::getMailerMessages()); // le bilan n'est envoyé que le lundi
    }

    private function mailer(): AccountMailer
    {
        $mailer = static::getContainer()->get(AccountMailer::class);
        \assert($mailer instanceof AccountMailer);

        return $mailer;
    }

    private function seed(
        string $email,
        string $activityAt,
        bool $enabled = true,
        bool $deletionRequested = false,
        bool $emailVerified = true,
    ): void {
        $tenant = Uuid::v7()->toRfc4122();
        $this->connection->executeStatement(
            'INSERT INTO app_user (id, tenant_id, email, password, roles, email_verified, deletion_requested_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Uuid::v7()->toRfc4122(), $tenant, $email, 'x', '[]', $emailVerified ? 'true' : 'false', $deletionRequested ? self::MONDAY : null],
        );
        $this->connection->executeStatement(
            "INSERT INTO profile (tenant_id, weekly_goal, timezone, weekly_report_enabled) VALUES (?, 5, 'Europe/Paris', ?)",
            [$tenant, $enabled ? 'true' : 'false'],
        );
        $this->connection->executeStatement(
            "INSERT INTO interaction (id, event_id, tenant_id, lead_id, type, payload, occurred_on)
             VALUES (?, ?, ?, ?, 'contacted', '{}', ?)",
            [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122(), $tenant, 'lead-'.$tenant, $activityAt],
        );
    }
}
