<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ReadModel;

use App\Account\Application\ReadModel\ProfileSettings;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Application\ReadModel\TodayBoard;
use App\Prospecting\Application\ReadModel\TodayView;
use App\Prospecting\Application\ReadModel\WeeklyProgress;
use App\Shared\Application\Clock;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;

/**
 * L'écran « Aujourd'hui » en SQL direct — FAIL-CLOSED tenant.
 * La progression et la série se calculent sur le journal `interaction`
 * (types `contacted` + `followed_up` = actes de démarchage, décision M1.3 n°3),
 * semaine du lundi dans le fuseau de l'utilisatrice (profil, défaut Europe/Paris).
 */
final class DoctrineTodayBoard implements TodayBoard
{
    private const OUTREACH_TYPES = ['contacted', 'followed_up'];

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
        private readonly LeadViewMapper $mapper,
        private readonly ProfileSettings $profile,
        private readonly Clock $clock,
    ) {
    }

    public function view(): TodayView
    {
        $tenant = $this->tenantContext->get()
            ?? throw new \LogicException('Today board queried without tenant in context — refusing to run an unscoped query.');
        $tenantId = $tenant->toString();

        $profile = $this->profile->current();
        $timezone = new \DateTimeZone($profile->timezone);
        $now = $this->clock->now()->setTimezone($timezone);

        // Relances dues : échéance atteinte (fin de journée locale), retards en premier.
        $endOfToday = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        $dueRows = $this->connection->fetchAllAssociative(
            sprintf(
                "SELECT %s %s WHERE l.tenant_id = :tenant AND l.next_follow_up_at IS NOT NULL
                 AND l.next_follow_up_at <= :endOfToday
                 AND l.status NOT IN ('WON', 'LOST', 'PAUSED')
                 ORDER BY l.next_follow_up_at ASC, CASE l.priority WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END",
                LeadViewMapper::COLUMNS,
                LeadViewMapper::FROM,
            ),
            ['tenant' => $tenantId, 'endOfToday' => $endOfToday],
        );

        // À contacter : priorité puis ancienneté (les plus vieilles d'abord).
        $toContactRows = $this->connection->fetchAllAssociative(
            sprintf(
                "SELECT %s %s WHERE l.tenant_id = :tenant AND l.status = 'TO_CONTACT'
                 ORDER BY CASE l.priority WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END, l.created_at ASC",
                LeadViewMapper::COLUMNS,
                LeadViewMapper::FROM,
            ),
            ['tenant' => $tenantId],
        );

        return new TodayView(
            array_map(fn (array $row): LeadView => $this->mapper->map($row), $dueRows),
            array_map(fn (array $row): LeadView => $this->mapper->map($row), $toContactRows),
            $this->weeklyProgress($tenantId, $profile->weeklyGoal, $timezone, $now),
        );
    }

    private function weeklyProgress(string $tenantId, int $goal, \DateTimeZone $timezone, \DateTimeImmutable $localNow): WeeklyProgress
    {
        // Actes par semaine ISO locale (occurred_on est stocké en UTC naïf).
        /** @var array<string, int> $byWeek */
        $byWeek = [];
        $rows = $this->connection->fetchAllAssociative(
            "SELECT to_char(date_trunc('week', (occurred_on AT TIME ZONE 'UTC') AT TIME ZONE :tz), 'IYYY-\"W\"IW') AS week, COUNT(*) AS acts
             FROM interaction
             WHERE tenant_id = :tenant AND type IN (:types)
             GROUP BY week",
            ['tenant' => $tenantId, 'tz' => $timezone->getName(), 'types' => self::OUTREACH_TYPES],
            ['types' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );
        foreach ($rows as $row) {
            if (\is_string($row['week'] ?? null) && is_numeric($row['acts'] ?? null)) {
                $byWeek[$row['week']] = (int) $row['acts'];
            }
        }

        $currentWeekKey = $localNow->format('o-\WW');
        $done = $byWeek[$currentWeekKey] ?? 0;

        // Série : semaines consécutives >= objectif en remontant. La semaine courante
        // compte si déjà atteinte ; sinon elle ne casse pas la série (elle est en cours).
        $streak = 0;
        $cursor = $localNow;
        if ($done >= $goal) {
            ++$streak;
        }
        $cursor = $cursor->modify('-7 days');
        while (($byWeek[$cursor->format('o-\WW')] ?? 0) >= $goal) {
            ++$streak;
            $cursor = $cursor->modify('-7 days');
        }

        return new WeeklyProgress($goal, $done, $streak);
    }
}
