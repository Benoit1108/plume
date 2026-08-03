<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — BILLING (GET /api/v1/admin/billing, ROLE_ADMIN). Abonnés par statut + revenu mensuel
 * ESTIMÉ (abonnés actifs × montant mensuel configuré — approximation : ignore mensuel/annuel).
 * Comptages seulement, sans PII ; connexion `admin` (rôle propriétaire, cross-tenant).
 */
#[AsController]
final class AdminBillingController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
        private readonly int $monthlyAmountEur,
    ) {
    }

    public function __invoke(): Response
    {
        /** @var array<string, int> $byStatus */
        $byStatus = ['trialing' => 0, 'active' => 0, 'past_due' => 0, 'canceled' => 0, 'comped' => 0];
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->admin->fetchAllAssociative('SELECT status, COUNT(*) AS cnt FROM subscription GROUP BY status');
        foreach ($rows as $row) {
            $status = \is_string($row['status'] ?? null) ? $row['status'] : '';
            if (\array_key_exists($status, $byStatus)) {
                $byStatus[$status] = is_numeric($row['cnt'] ?? null) ? (int) $row['cnt'] : 0;
            }
        }

        return new JsonResponse([
            'byStatus' => $byStatus,
            // MRR estimé : abonnés actifs × montant mensuel. « Estimé » car l'annuel n'est pas pondéré.
            'estimatedMonthlyRevenue' => $byStatus['active'] * $this->monthlyAmountEur,
            'monthlyAmount' => $this->monthlyAmountEur,
        ]);
    }
}
