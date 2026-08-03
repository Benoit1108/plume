<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — COURBES & ENTONNOIR (GET /api/v1/admin/trends, ROLE_ADMIN). Croissance dans le temps
 * (comptes ACTIFS par semaine, 12 dernières semaines ISO) + entonnoir d'acquisition (inscription →
 * email vérifié → 1re piste → actif 30 j). Comptages agrégés seulement, sans PII (ADR-0026) ;
 * cross-tenant via la connexion `admin` (rôle propriétaire).
 */
#[AsController]
final class AdminTrendsController
{
    private const int WEEKS = 12;

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        return new JsonResponse([
            'weeklyActive' => $this->weeklyActive(),
            'funnel' => $this->funnel(),
        ]);
    }

    /** @return list<array{week: string, count: int}> comptes actifs par semaine ISO, plus ancienne d'abord */
    private function weeklyActive(): array
    {
        // Comptes distincts ayant au moins un acte dans la semaine (contact/relance/réponse/…).
        /** @var array<string, int> $byWeek */
        $byWeek = [];
        /** @var list<array{week: string, cnt: int|string}> $rows */
        $rows = $this->admin->fetchAllAssociative(
            "SELECT to_char(date_trunc('week', occurred_on), 'IYYY-\"W\"IW') AS week, COUNT(DISTINCT tenant_id) AS cnt
             FROM interaction
             WHERE occurred_on > NOW() - INTERVAL '12 weeks'
             GROUP BY 1",
        );
        foreach ($rows as $row) {
            $byWeek[(string) $row['week']] = self::toInt($row['cnt']);
        }

        $weeks = [];
        $monday = $this->clock->now()->modify('monday this week')->setTime(0, 0);
        for ($i = self::WEEKS - 1; $i >= 0; --$i) {
            $weekStart = $monday->modify(sprintf('-%d days', 7 * $i));
            $weeks[] = ['week' => $weekStart->format('Y-m-d'), 'count' => $byWeek[$weekStart->format('o-\WW')] ?? 0];
        }

        return $weeks;
    }

    /** @return array{signedUp: int, verified: int, activated: int, active30d: int} */
    private function funnel(): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->admin->fetchAssociative(<<<'SQL'
            SELECT
                COUNT(*) AS signed_up,
                COUNT(*) FILTER (WHERE email_verified = true) AS verified,
                COUNT(*) FILTER (WHERE email_verified = true
                    AND EXISTS (SELECT 1 FROM lead l WHERE l.tenant_id = u.tenant_id)) AS activated,
                COUNT(*) FILTER (WHERE email_verified = true
                    AND EXISTS (SELECT 1 FROM interaction i WHERE i.tenant_id = u.tenant_id
                                AND i.occurred_on > NOW() - INTERVAL '30 days')) AS active30d
            FROM app_user u
            WHERE u.roles::text NOT LIKE '%ROLE_ADMIN%' AND u.deletion_requested_at IS NULL
            SQL);

        return [
            'signedUp' => self::toInt($row['signed_up'] ?? 0),
            'verified' => self::toInt($row['verified'] ?? 0),
            'activated' => self::toInt($row['activated'] ?? 0),
            'active30d' => self::toInt($row['active30d'] ?? 0),
        ];
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
