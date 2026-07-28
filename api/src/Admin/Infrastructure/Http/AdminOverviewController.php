<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — vue d'ensemble (GET /api/v1/admin/overview, ROLE_ADMIN). Lectures CROSS-TENANT via
 * la connexion `admin` (rôle propriétaire, contourne la RLS — lecteur légitime, ADR-0023) : des
 * COMPTAGES uniquement, jamais de contenu métier (minimisation — le back-office n'a pas à lire les
 * données des traductrices). Contrôleur simple (hors API Platform : outil interne, pas du contrat).
 */
#[AsController]
final class AdminOverviewController
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
                COUNT(*) FILTER (WHERE roles::text NOT LIKE '%ROLE_ADMIN%' AND email_verified = false) AS unverified,
                COUNT(*) FILTER (WHERE roles::text NOT LIKE '%ROLE_ADMIN%' AND deletion_requested_at IS NOT NULL) AS pending_deletion
            FROM app_user
            SQL);

        /** @var array<string, mixed> $business */
        $business = (array) $this->admin->fetchAssociative(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM organization) AS organizations,
                (SELECT COUNT(*) FROM lead) AS leads,
                (SELECT COUNT(*) FROM outbound_message WHERE status = 'SENT') AS messages_sent,
                (SELECT COUNT(*) FROM candidate_lead WHERE status = 'PENDING') AS candidates_pending,
                (SELECT COUNT(*) FROM connected_mailbox WHERE status = 'CONNECTED') AS mailboxes_connected,
                (SELECT COUNT(*) FROM connected_mailbox WHERE status = 'ERROR') AS mailboxes_error
            SQL);

        // Profondeur des files Messenger (santé système : un `failed` qui grossit = incident).
        /** @var list<array{queue_name: string, depth: int|string}> $queues */
        $queues = $this->admin->fetchAllAssociative(
            'SELECT queue_name, COUNT(*) AS depth FROM messenger_messages GROUP BY queue_name',
        );
        $queueDepths = [];
        foreach ($queues as $queue) {
            $queueDepths[$queue['queue_name']] = self::toInt($queue['depth']);
        }

        return new JsonResponse([
            'accounts' => [
                'total' => self::toInt($accounts['total'] ?? 0),
                'unverified' => self::toInt($accounts['unverified'] ?? 0),
                'pendingDeletion' => self::toInt($accounts['pending_deletion'] ?? 0),
            ],
            'business' => [
                'organizations' => self::toInt($business['organizations'] ?? 0),
                'leads' => self::toInt($business['leads'] ?? 0),
                'messagesSent' => self::toInt($business['messages_sent'] ?? 0),
                'candidatesPending' => self::toInt($business['candidates_pending'] ?? 0),
                'mailboxesConnected' => self::toInt($business['mailboxes_connected'] ?? 0),
                'mailboxesError' => self::toInt($business['mailboxes_error'] ?? 0),
            ],
            'queues' => $queueDepths,
        ]);
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
