<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Billing\Application\Subscriptions;
use App\Shared\Infrastructure\Audit\AuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Back-office — ACCÈS OFFERT (POST /api/v1/admin/accounts/{tenantId}/comp, ROLE_ADMIN). Bascule un
 * compte en accès gratuit complet (`comped`, hors Stripe) ou le retire, selon `{ "comped": bool }`.
 * Tracé au journal d'audit. Idempotent.
 */
#[AsController]
final class AdminCompController
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.admin_connection')]
        private readonly Connection $admin,
        private readonly Subscriptions $subscriptions,
        private readonly Security $security,
        private readonly AuditLogger $audit,
    ) {
    }

    public function __invoke(string $tenantId, Request $request): Response
    {
        $exists = $this->admin->fetchOne(
            "SELECT 1 FROM app_user WHERE tenant_id = :tenant AND roles::text NOT LIKE '%ROLE_ADMIN%'",
            ['tenant' => $tenantId],
        );
        if (false === $exists) {
            throw new NotFoundHttpException('Unknown account.');
        }

        $payload = json_decode($request->getContent(), true);
        $comped = \is_array($payload) && true === ($payload['comped'] ?? null);

        if ($comped) {
            $this->subscriptions->comp($tenantId);
        } else {
            $this->subscriptions->uncomp($tenantId);
        }

        $this->audit->record(
            $this->security->getUser()?->getUserIdentifier() ?? 'admin',
            $comped ? 'admin.access_comped' : 'admin.access_uncomped',
            $tenantId,
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
