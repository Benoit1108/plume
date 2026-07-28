<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit HORS TENANT (V2 back-office + backlog ADR-0025) : trace les actions sensibles
 * (suppression demandée, purge exécutée, actions admin) dans `audit_log` — une table qui survit à
 * la purge RGPD du tenant (son but : prouver que l'effacement a eu lieu). Best-effort : l'audit ne
 * doit JAMAIS faire échouer l'action métier qu'il trace (échec → log applicatif seulement).
 */
final class AuditLogger
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string               $actor   identifiant de qui agit (email, ou "system" pour un tick)
     * @param string               $action  code stable (ex. account.deletion_requested)
     * @param string               $target  identifiant technique de la cible (tenant id, email…)
     * @param array<string, mixed> $details contexte NON sensible (jamais de PII au-delà des identifiants)
     */
    public function record(string $actor, string $action, string $target, array $details = []): void
    {
        try {
            $this->connection->executeStatement(
                'INSERT INTO audit_log (id, actor, action, target, details, occurred_at)
                 VALUES (:id, :actor, :action, :target, :details, NOW())',
                [
                    'id' => Uuid::v7()->toRfc4122(),
                    'actor' => $actor,
                    'action' => $action,
                    'target' => $target,
                    'details' => json_encode($details, \JSON_THROW_ON_ERROR),
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error('Audit log write failed.', ['action' => $action, 'target' => $target, 'error' => $e->getMessage()]);
        }
    }
}
