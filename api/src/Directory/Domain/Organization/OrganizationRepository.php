<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization;

interface OrganizationRepository
{
    public function save(Organization $organization): void;

    /** @throws \RuntimeException si l'organisation est introuvable */
    public function get(OrganizationId $id): Organization;

    /**
     * Liste (scoping tenant automatique via le filtre Doctrine) filtrée par type et/ou texte.
     *
     * @return Organization[]
     */
    public function findMatching(?OrganizationType $type, ?string $query): array;
}
