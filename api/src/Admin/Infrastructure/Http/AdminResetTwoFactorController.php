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
 * Back-office — DERNIER RECOURS de récupération 2FA (revue globale P0-3, POST /api/v1/admin/accounts/
 * {tenantId}/reset-2fa, ROLE_ADMIN). Si l'utilisatrice perd son téléphone ET ses codes de secours,
 * elle est autrement enfermée dehors définitivement. Le support désactive la 2FA après vérification
 * d'identité HORS BANDE (procédure humaine) ; tracé au journal d'audit (qui a réinitialisé quoi).
 * Ne touche pas au mot de passe. Idempotent.
 */
#[AsController]
final class AdminResetTwoFactorController
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
        $exists = $this->admin->fetchOne(
            "SELECT 1 FROM app_user WHERE tenant_id = :tenant AND roles::text NOT LIKE '%ROLE_ADMIN%'",
            ['tenant' => $tenantId],
        );
        if (false === $exists) {
            throw new NotFoundHttpException('Unknown account.');
        }

        $this->admin->executeStatement(
            'UPDATE app_user SET totp_secret = NULL, totp_pending_secret = NULL, totp_last_used_step = NULL, backup_codes = NULL
             WHERE tenant_id = :tenant',
            ['tenant' => $tenantId],
        );

        $this->audit->record(
            $this->security->getUser()?->getUserIdentifier() ?? 'admin',
            'admin.2fa_reset',
            $tenantId,
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
