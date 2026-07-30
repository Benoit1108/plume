<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Http;

use App\Prospecting\Application\Command\SetEstimatedValue\SetEstimatedValue;
use App\Prospecting\Domain\Lead\Exception\LeadNotFound;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fixe la valeur estimée d'une piste (PATCH /api/v1/leads/{id}/estimated-value, corps
 * `{estimatedValue: int|null}` — null efface). Action dédiée (comme les transitions) plutôt qu'un
 * PATCH générique : le pipeline n'a pas de PATCH de champs libres.
 */
#[AsController]
final class SetEstimatedValueController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $raw = \is_array($payload) ? ($payload['estimatedValue'] ?? null) : null;
        $value = is_numeric($raw) ? (int) $raw : null;

        try {
            $this->commandBus->dispatch(new SetEstimatedValue($id, $value, $this->tenantContext->require()->toString()));
        } catch (LeadNotFound) {
            throw new NotFoundHttpException('Unknown lead.');
        } catch (InvalidValue $e) {
            return new JsonResponse(['detail' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
