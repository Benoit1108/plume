<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Infrastructure\ReadModel\AccountDirectory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — comptes (GET /api/v1/admin/accounts, ROLE_ADMIN). Liste les comptes des traductrices
 * (les administrateurs n'y figurent pas) avec des COMPTAGES par tenant — jamais de contenu métier
 * (minimisation). Recherche `q`, filtre `status` (all|verified|unverified|deleting), tri `sort`
 * (email|leads|created), bornée à 100 lignes. Source partagée avec l'export CSV.
 */
#[AsController]
final class AdminAccountsController
{
    public function __construct(private readonly AccountDirectory $accounts)
    {
    }

    public function __invoke(Request $request): Response
    {
        return new JsonResponse([
            'accounts' => $this->accounts->list(
                trim((string) $request->query->get('q', '')),
                (string) $request->query->get('status', 'all'),
                (string) $request->query->get('sort', 'email'),
                100,
            ),
        ]);
    }
}
