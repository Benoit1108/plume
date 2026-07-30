<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Http;

use App\Directory\Application\Catalog\DirectoryCatalog;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Annuaire suggéré (GET /api/v1/directory/catalog?q=) : cibles de référence à ajouter au Répertoire.
 * Marque `alreadyImported` les entrées dont le nom est DÉJÀ dans le Répertoire du tenant (l'unicité
 * du nom par tenant fait foi) — pour ne proposer que ce qui manque.
 */
#[AsController]
final class CatalogController
{
    public function __construct(
        private readonly DirectoryCatalog $catalog,
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $q = mb_strtolower(trim((string) $request->query->get('q', '')));

        /** @var list<string> $names */
        $names = $this->connection->fetchFirstColumn(
            'SELECT LOWER(name) FROM organization WHERE tenant_id = :tenant',
            ['tenant' => $this->tenantContext->require()->toString()],
        );
        $existing = array_flip($names);

        $entries = [];
        foreach ($this->catalog->all() as $entry) {
            if ('' !== $q && !str_contains(mb_strtolower($entry->name), $q)) {
                continue;
            }
            $entries[] = [
                'id' => $entry->id,
                'name' => $entry->name,
                'type' => $entry->type,
                'country' => $entry->country,
                'website' => $entry->website,
                'languages' => $entry->languages,
                'segments' => $entry->segments,
                'note' => $entry->note,
                'alreadyImported' => isset($existing[mb_strtolower($entry->name)]),
            ];
        }

        return new JsonResponse(['entries' => $entries]);
    }
}
