<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization;

interface OrganizationRepository
{
    public function save(Organization $organization): void;

    /** @throws \RuntimeException si l'organisation est introuvable */
    public function get(OrganizationId $id): Organization;
}
