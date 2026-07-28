<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Shared\Infrastructure\Audit\AuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Back-office — demande de suppression RGPD côté SUPPORT (POST /api/v1/admin/accounts/{tenantId}/
 * request-deletion, ROLE_ADMIN) : quand une utilisatrice le demande par email plutôt que par
 * l'appli. Même sémantique que le self-service : soft-delete immédiat (accès coupé, relèves
 * stoppées), purge après le délai de grâce de 30 j (tick V2.0-a2). Idempotent (la 1re demande fait
 * foi), sessions révoquées, TRACÉ au journal d'audit (qui a déclenché quoi). Un compte admin n'est
 * pas supprimable par cette voie.
 */
#[AsController]
final class AdminRequestAccountDeletionController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
        private readonly Security $security,
        private readonly AuditLogger $audit,
    ) {
    }

    public function __invoke(string $tenantId): Response
    {
        /** @var array{email: string}|false $target */
        $target = $this->admin->fetchAssociative(
            "SELECT email FROM app_user WHERE tenant_id = :tenant AND roles::text NOT LIKE '%ROLE_ADMIN%'",
            ['tenant' => $tenantId],
        );
        if (false === $target) {
            throw new NotFoundHttpException('Unknown account.');
        }

        // Idempotent : la première demande (self-service ou support) fait foi.
        $this->admin->executeStatement(
            'UPDATE app_user SET deletion_requested_at = NOW() WHERE tenant_id = :tenant AND deletion_requested_at IS NULL',
            ['tenant' => $tenantId],
        );
        $this->admin->executeStatement(
            'DELETE FROM refresh_tokens WHERE username = :email',
            ['email' => $target['email']],
        );

        $admin = $this->security->getUser();
        $this->audit->record($admin?->getUserIdentifier() ?? 'admin', 'admin.account_deletion_requested', $tenantId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
