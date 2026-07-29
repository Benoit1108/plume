<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — STATUT SYSTÈME (GET /api/v1/admin/status, ROLE_ADMIN). État OPÉRATIONNEL interne,
 * distinct de la sonde publique `/health` (load-balancer) : profondeur des files Messenger, ÂGE du
 * backlog (un backlog qui vieillit = worker bloqué), file `failed` (incidents à rejouer), boîtes en
 * erreur (reconnexion à demander). Connexion `admin` (rôle propriétaire) : `messenger_messages` est
 * hors tenant, les autres comptages sont cross-tenant (ADR-0026).
 */
#[AsController]
final class AdminStatusController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
    ) {
    }

    public function __invoke(): Response
    {
        // La table Doctrine ne garde que les messages NON traités (les livrés sont supprimés) :
        // COUNT par queue = profondeur EN ATTENTE. Un `failed` non nul = des messages à rejouer.
        /** @var list<array{queue_name: string, depth: int|string}> $rows */
        $rows = $this->admin->fetchAllAssociative(
            'SELECT queue_name, COUNT(*) AS depth FROM messenger_messages WHERE delivered_at IS NULL GROUP BY queue_name',
        );
        $queues = [];
        foreach ($rows as $row) {
            $queues[$row['queue_name']] = self::toInt($row['depth']);
        }

        // Âge (secondes) du plus vieux message EN ATTENTE hors `failed` : proxy « les workers suivent ».
        $backlogAge = $this->admin->fetchOne(
            "SELECT EXTRACT(EPOCH FROM (NOW() - MIN(created_at))) FROM messenger_messages WHERE delivered_at IS NULL AND queue_name <> 'failed'",
        );

        $mailboxesError = $this->admin->fetchOne("SELECT COUNT(*) FROM connected_mailbox WHERE status = 'ERROR'");

        return new JsonResponse([
            'db' => 'ok', // atteindre ce point PROUVE que la base répond.
            'queues' => $queues,
            'failed' => $queues['failed'] ?? 0,
            'backlogAgeSeconds' => is_numeric($backlogAge) ? (int) $backlogAge : 0,
            'mailboxesError' => self::toInt($mailboxesError),
        ]);
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
