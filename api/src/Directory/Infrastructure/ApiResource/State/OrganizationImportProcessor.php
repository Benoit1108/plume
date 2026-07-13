<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Directory\Application\Command\AddContact\AddContact;
use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Directory\Application\Query\ListOrganizations\ListOrganizations;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Infrastructure\ApiResource\OrganizationImportResource;
use App\Directory\Infrastructure\Import\CsvOrganizationParser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;
use App\Shared\Domain\Exception\DomainError;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Orchestre l'import : parse le CSV, dédoublonne par nom (déjà en base ou dans le lot),
 * puis dispatche une commande par ligne. Le tenant vient du contexte (JWT).
 *
 * @implements ProcessorInterface<OrganizationImportResource, OrganizationImportResource>
 */
final class OrganizationImportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly TenantContext $tenantContext,
        private readonly CsvOrganizationParser $parser,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationImportResource
    {
        $tenantId = $this->currentTenantId();

        try {
            $parsed = $this->parser->parse($data->content, $data->delimiter);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        if (\count($parsed->rows) > OrganizationImportResource::MAX_ROWS) {
            throw new UnprocessableEntityHttpException(sprintf('Fichier trop volumineux : %d lignes (maximum %d par import). Scindez-le.', \count($parsed->rows), OrganizationImportResource::MAX_ROWS));
        }

        $result = new OrganizationImportResource();
        $result->errors = $parsed->errors;
        $result->failed = \count($parsed->errors);

        /** @var Organization[] $existing */
        $existing = $this->queryBus->ask(new ListOrganizations(null, null));
        $seen = [];
        foreach ($existing as $organization) {
            $seen[$this->key($organization->name())] = true;
        }

        foreach ($parsed->rows as $row) {
            $key = $this->key($row->name);
            if (isset($seen[$key])) {
                ++$result->skipped;
                continue;
            }

            $organizationId = Uuid::v7()->toRfc4122();
            try {
                $this->commandBus->dispatch(new CreateOrganization(
                    $organizationId,
                    $tenantId,
                    $row->name,
                    $row->type,
                    $row->website,
                    $row->country,
                    $row->languages,
                    $row->segments,
                    $row->notes,
                ));
            } catch (DomainError $exception) {
                ++$result->failed;
                $result->errors[] = ['line' => $row->line, 'message' => sprintf('« %s » : %s', $row->name, $exception->getMessage())];
                continue;
            } catch (\Throwable $exception) {
                // Panne technique : rien d'interne ne doit fuiter vers le client.
                $this->logger->error('Import CSV : échec inattendu.', ['line' => $row->line, 'exception' => $exception]);
                ++$result->failed;
                $result->errors[] = ['line' => $row->line, 'message' => sprintf('« %s » : erreur interne, ligne non importée.', $row->name)];
                continue;
            }

            $seen[$key] = true;
            ++$result->imported;

            if (null !== $row->contactName) {
                try {
                    $this->commandBus->dispatch(new AddContact(
                        $organizationId,
                        Uuid::v7()->toRfc4122(),
                        $row->contactName,
                        $row->contactRole,
                        $row->contactEmail,
                        $row->contactPhone,
                        null,
                        null,
                    ));
                } catch (DomainError $exception) {
                    $result->errors[] = ['line' => $row->line, 'message' => sprintf('« %s » importée, mais contact ignoré : %s', $row->name, $exception->getMessage())];
                } catch (\Throwable $exception) {
                    $this->logger->error('Import CSV : échec inattendu sur le contact.', ['line' => $row->line, 'exception' => $exception]);
                    $result->errors[] = ['line' => $row->line, 'message' => sprintf('« %s » importée, mais contact ignoré (erreur interne).', $row->name)];
                }
            }
        }

        return $result;
    }

    private function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function currentTenantId(): string
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new \LogicException('No tenant in context.');
        }

        return $tenant->toString();
    }
}
