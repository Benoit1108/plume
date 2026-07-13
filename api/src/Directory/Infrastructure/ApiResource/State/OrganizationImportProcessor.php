<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Directory\Application\Import\OrganizationImporter;
use App\Directory\Infrastructure\ApiResource\OrganizationImportResource;
use App\Directory\Infrastructure\Import\CsvOrganizationParser;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Adaptateur HTTP de l'import : parse le CSV (format = détail d'infrastructure),
 * délègue l'orchestration à l'Application (OrganizationImporter), mappe le rapport.
 *
 * @implements ProcessorInterface<OrganizationImportResource, OrganizationImportResource>
 */
final class OrganizationImportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OrganizationImporter $importer,
        private readonly TenantContext $tenantContext,
        private readonly CsvOrganizationParser $parser,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationImportResource
    {
        $tenant = $this->tenantContext->get() ?? throw new \LogicException('No tenant in context.');

        try {
            $parsed = $this->parser->parse($data->content, $data->delimiter);
        } catch (\InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        if (\count($parsed->rows) > OrganizationImportResource::MAX_ROWS) {
            throw new UnprocessableEntityHttpException(sprintf('Fichier trop volumineux : %d lignes (maximum %d par import). Scindez-le.', \count($parsed->rows), OrganizationImportResource::MAX_ROWS));
        }

        $report = $this->importer->import($tenant->toString(), $parsed->rows, $parsed->errors);

        $result = new OrganizationImportResource();
        $result->imported = $report->imported;
        $result->skipped = $report->skipped;
        $result->failed = $report->failed;
        $result->errors = $report->errors;

        return $result;
    }
}
