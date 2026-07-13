<?php

declare(strict_types=1);

namespace App\Directory\Application\Import;

use App\Directory\Application\Command\AddContact\AddContact;
use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\Exception\DomainError;

/**
 * Orchestration de l'import : dédoublonnage par nom (base + lot), puis une commande
 * par ligne — chaque ligne a sa propre transaction, une ligne fautive n'annule pas
 * les autres. Les erreurs MÉTIER sont collectées ligne par ligne ; une panne
 * technique, elle, interrompt l'import (500) au lieu d'être maquillée.
 */
final class OrganizationImporter
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly OrganizationRepository $organizations,
        private readonly IdGenerator $ids,
    ) {
    }

    /**
     * @param ImportedOrganizationRow[]               $rows
     * @param list<array{line: int, message: string}> $parseErrors erreurs de parsing (lignes inexploitables)
     */
    public function import(string $tenantId, array $rows, array $parseErrors = []): ImportReport
    {
        $report = new ImportReport();
        foreach ($parseErrors as $error) {
            $report->addError($error['line'], $error['message']);
            ++$report->failed;
        }

        // Dédoublonnage en une requête : noms déjà pris en base (le filtre tenant s'applique).
        $seen = array_fill_keys(
            $this->organizations->takenNamesAmong(array_map(static fn (ImportedOrganizationRow $row): string => $row->name, $rows)),
            true,
        );

        foreach ($rows as $row) {
            $key = mb_strtolower(trim($row->name));
            if (isset($seen[$key])) {
                ++$report->skipped;
                continue;
            }

            $organizationId = $this->ids->generate();
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
                ++$report->failed;
                $report->addError($row->line, sprintf('« %s » : %s', $row->name, $exception->getMessage()));
                continue;
            }

            $seen[$key] = true;
            ++$report->imported;

            if (null !== $row->contactName) {
                try {
                    $this->commandBus->dispatch(new AddContact(
                        $organizationId,
                        $this->ids->generate(),
                        $row->contactName,
                        $row->contactRole,
                        $row->contactEmail,
                        $row->contactPhone,
                        null,
                        null,
                    ));
                } catch (DomainError $exception) {
                    $report->addError($row->line, sprintf('« %s » importée, mais contact ignoré : %s', $row->name, $exception->getMessage()));
                }
            }
        }

        return $report;
    }
}
