<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ReadModel;

use App\Account\Application\ReadModel\ProfileSettings;
use App\Prospecting\Application\ReadModel\Dashboard;
use App\Prospecting\Application\ReadModel\DashboardView;
use App\Prospecting\Application\ReadModel\PipelineSlice;
use App\Prospecting\Application\ReadModel\SegmentStats;
use App\Prospecting\Application\ReadModel\WeekActivity;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Shared\Application\Clock;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Le tableau de bord en SQL direct — FAIL-CLOSED tenant (ADR-0013), aucune
 * projection dédiée (décision M1.5 n°6). Les taux se calculent sur le JOURNAL
 * (lead_id distinct par type d'acte, décision n°2) : l'historique survit aux
 * transitions de statut. Semaines ISO dans le fuseau du profil (pattern M1.3).
 */
final class DoctrineDashboard implements Dashboard
{
    use HydratesRows;

    private const OUTREACH_TYPES = ['contacted', 'followed_up'];
    private const WEEKS_SHOWN = 8;

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
        private readonly ProfileSettings $profile,
        private readonly Clock $clock,
    ) {
    }

    public function view(): DashboardView
    {
        $tenant = $this->tenantContext->require();
        $tenantId = $tenant->toString();

        $profile = $this->profile->current();
        $timezone = new \DateTimeZone($profile->timezone);
        $localNow = $this->clock->now()->setTimezone($timezone);

        $rates = $this->leadRates($tenantId);

        return new DashboardView(
            contacted: $rates['contacted'],
            replied: $rates['replied'],
            won: $rates['won'],
            lost: $rates['lost'],
            activeLeads: $this->activeLeads($tenantId),
            outreachThisMonth: $this->outreachThisMonth($tenantId, $timezone, $localNow),
            weeklyTarget: $profile->weeklyGoal,
            pipeline: $this->pipeline($tenantId),
            weeklyActivity: $this->weeklyActivity($tenantId, $timezone, $localNow),
            segments: $this->segments($tenantId),
        );
    }

    /** @return array{contacted: int, replied: int, won: int, lost: int} */
    private function leadRates(string $tenantId): array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT COUNT(DISTINCT CASE WHEN type = 'contacted' THEN lead_id END) AS contacted,
                    COUNT(DISTINCT CASE WHEN type = 'reply' THEN lead_id END) AS replied,
                    COUNT(DISTINCT CASE WHEN type = 'won' THEN lead_id END) AS won,
                    COUNT(DISTINCT CASE WHEN type = 'lost' THEN lead_id END) AS lost
             FROM interaction WHERE tenant_id = :tenant",
            ['tenant' => $tenantId],
        );

        return [
            'contacted' => $this->int($row ?: [], 'contacted'),
            'replied' => $this->int($row ?: [], 'replied'),
            'won' => $this->int($row ?: [], 'won'),
            'lost' => $this->int($row ?: [], 'lost'),
        ];
    }

    private function activeLeads(string $tenantId): int
    {
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM lead WHERE tenant_id = :tenant AND status NOT IN ('WON', 'LOST')",
            ['tenant' => $tenantId],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    private function outreachThisMonth(string $tenantId, \DateTimeZone $timezone, \DateTimeImmutable $localNow): int
    {
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM interaction
             WHERE tenant_id = :tenant AND type IN (:types)
             AND to_char((occurred_on AT TIME ZONE 'UTC') AT TIME ZONE :tz, 'YYYY-MM') = :month",
            ['tenant' => $tenantId, 'tz' => $timezone->getName(), 'types' => self::OUTREACH_TYPES, 'month' => $localNow->format('Y-m')],
            ['types' => ArrayParameterType::STRING],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /** @return PipelineSlice[] ordre canonique du kanban, statuts vides omis */
    private function pipeline(string $tenantId): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT status, COUNT(*) FROM lead WHERE tenant_id = :tenant GROUP BY status',
            ['tenant' => $tenantId],
        );

        $slices = [];
        foreach (PipelineStatus::cases() as $status) {
            $count = $rows[$status->value] ?? 0;
            if (is_numeric($count) && (int) $count > 0) {
                $slices[] = new PipelineSlice($status->value, (int) $count);
            }
        }

        return $slices;
    }

    /** @return WeekActivity[] les 8 dernières semaines ISO locales, la plus ancienne d'abord */
    private function weeklyActivity(string $tenantId, \DateTimeZone $timezone, \DateTimeImmutable $localNow): array
    {
        /** @var array<string, int> $byWeek */
        $byWeek = [];
        $rows = $this->connection->fetchAllAssociative(
            "SELECT to_char(date_trunc('week', (occurred_on AT TIME ZONE 'UTC') AT TIME ZONE :tz), 'IYYY-\"W\"IW') AS week, COUNT(*) AS acts
             FROM interaction
             WHERE tenant_id = :tenant AND type IN (:types)
             GROUP BY week",
            ['tenant' => $tenantId, 'tz' => $timezone->getName(), 'types' => self::OUTREACH_TYPES],
            ['types' => ArrayParameterType::STRING],
        );
        foreach ($rows as $row) {
            if (\is_string($row['week'] ?? null) && is_numeric($row['acts'] ?? null)) {
                $byWeek[$row['week']] = (int) $row['acts'];
            }
        }

        $weeks = [];
        $monday = $localNow->modify('monday this week')->setTime(0, 0);
        for ($i = self::WEEKS_SHOWN - 1; $i >= 0; --$i) {
            $weekStart = $monday->modify(sprintf('-%d days', 7 * $i));
            $weeks[] = new WeekActivity(
                $weekStart->format('Y-m-d'),
                $byWeek[$weekStart->format('o-\WW')] ?? 0,
            );
        }

        return $weeks;
    }

    /** @return SegmentStats[] segments ayant au moins une piste, ordre canonique */
    private function segments(string $tenantId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT l.segment,
                    COUNT(DISTINCT CASE WHEN i.type = 'contacted' THEN l.id END) AS contacted,
                    COUNT(DISTINCT CASE WHEN i.type = 'reply' THEN l.id END) AS replied,
                    COUNT(DISTINCT CASE WHEN i.type = 'won' THEN l.id END) AS won
             FROM lead l
             LEFT JOIN interaction i ON i.lead_id = l.id AND i.tenant_id = l.tenant_id
             WHERE l.tenant_id = :tenant
             GROUP BY l.segment",
            ['tenant' => $tenantId],
        );

        /** @var array<string, SegmentStats> $bySegment */
        $bySegment = [];
        foreach ($rows as $row) {
            if (!\is_string($row['segment'] ?? null)) {
                continue;
            }
            $bySegment[$row['segment']] = new SegmentStats(
                $row['segment'],
                $this->int($row, 'contacted'),
                $this->int($row, 'replied'),
                $this->int($row, 'won'),
            );
        }

        $ordered = [];
        foreach (Segment::cases() as $segment) {
            if (isset($bySegment[$segment->value])) {
                $ordered[] = $bySegment[$segment->value];
            }
        }

        return $ordered;
    }
}
