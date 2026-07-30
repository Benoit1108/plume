<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Http;

use App\Directory\Application\Catalog\DirectoryCatalog;
use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Directory\Domain\Organization\Exception\OrganizationNameAlreadyUsed;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Ajoute une entrée de l'annuaire suggéré au Répertoire du tenant (POST /api/v1/directory/catalog/import,
 * corps `{id}`). Crée une Organisation via la commande existante (dédup par nom du domaine) : si le nom
 * est déjà pris → 409 (déjà dans le Répertoire), entrée inconnue → 404.
 */
#[AsController]
final class ImportCatalogEntryController
{
    public function __construct(
        private readonly DirectoryCatalog $catalog,
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $id = \is_array($payload) && \is_string($payload['id'] ?? null) ? $payload['id'] : '';

        $entry = $this->catalog->find($id) ?? throw new NotFoundHttpException('Unknown catalog entry.');

        try {
            $this->commandBus->dispatch(new CreateOrganization(
                Uuid::v7()->toRfc4122(),
                $this->tenantContext->require()->toString(),
                $entry->name,
                $entry->type,
                $entry->website,
                $entry->country,
                $entry->languages,
                $entry->segments,
                $entry->note,
            ));
        } catch (OrganizationNameAlreadyUsed) {
            return new JsonResponse(['detail' => 'already_in_directory'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['name' => $entry->name], Response::HTTP_CREATED);
    }
}
