<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — MÉTRIQUES PRODUIT (GET /api/v1/admin/metrics, ROLE_ADMIN). KPIs agrégés
 * RESPECTUEUX DE LA VIE PRIVÉE : uniquement des COMPTAGES/répartitions, jamais de contenu ni de PII
 * (ADR-0026). Cross-tenant via la connexion `admin` (rôle propriétaire).
 */
#[AsController]
final class AdminMetricsController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
    ) {
    }

    public function __invoke(): Response
    {
        /** @var array<string, mixed> $accounts */
        $accounts = (array) $this->admin->fetchAssociative(<<<'SQL'
            SELECT
                COUNT(*) FILTER (WHERE roles::text NOT LIKE '%ROLE_ADMIN%') AS total,
                COUNT(*) FILTER (WHERE roles::text NOT LIKE '%ROLE_ADMIN%' AND email_verified = true) AS verified
            FROM app_user
            SQL);

        // Comptes ACTIFS : au moins une interaction (contact/réponse/envoi) dans les 30 derniers jours.
        $active30d = $this->admin->fetchOne(
            "SELECT COUNT(DISTINCT tenant_id) FROM interaction WHERE occurred_on > NOW() - INTERVAL '30 days'",
        );

        // Inscriptions par semaine (8 dernières semaines), admins exclus.
        /** @var list<array{week: string, cnt: int|string}> $signupRows */
        $signupRows = $this->admin->fetchAllAssociative(<<<'SQL'
            SELECT to_char(date_trunc('week', created_at), 'YYYY-MM-DD') AS week, COUNT(*) AS cnt
            FROM app_user
            WHERE roles::text NOT LIKE '%ROLE_ADMIN%' AND created_at > NOW() - INTERVAL '8 weeks'
            GROUP BY 1 ORDER BY 1
            SQL);
        $signups = array_map(
            static fn (array $row): array => ['week' => (string) $row['week'], 'count' => self::toInt($row['cnt'])],
            $signupRows,
        );

        // Répartition des pistes par statut (santé du pipeline global).
        /** @var list<array{status: string, cnt: int|string}> $statusRows */
        $statusRows = $this->admin->fetchAllAssociative('SELECT status, COUNT(*) AS cnt FROM lead GROUP BY status');
        $leadsByStatus = [];
        foreach ($statusRows as $row) {
            $leadsByStatus[(string) $row['status']] = self::toInt($row['cnt']);
        }

        /** @var array<string, mixed> $totals */
        $totals = (array) $this->admin->fetchAssociative(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM organization) AS organizations,
                (SELECT COUNT(*) FROM lead) AS leads,
                (SELECT COUNT(*) FROM outbound_message WHERE status = 'SENT') AS messages_sent
            SQL);

        return new JsonResponse([
            'accounts' => [
                'total' => self::toInt($accounts['total'] ?? 0),
                'verified' => self::toInt($accounts['verified'] ?? 0),
                'active30d' => self::toInt($active30d),
            ],
            'signups' => $signups,
            'leadsByStatus' => $leadsByStatus,
            'totals' => [
                'organizations' => self::toInt($totals['organizations'] ?? 0),
                'leads' => self::toInt($totals['leads'] ?? 0),
                'messagesSent' => self::toInt($totals['messages_sent'] ?? 0),
            ],
        ]);
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
