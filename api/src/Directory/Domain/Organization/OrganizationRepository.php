<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization;

interface OrganizationRepository
{
    public function save(Organization $organization): void;

    /** @throws Exception\OrganizationNotFound si introuvable (dans le périmètre du tenant) */
    public function get(OrganizationId $id): Organization;

    /** Le nom est-il déjà pris (insensible à la casse), hors l'organisation ignorée ? */
    public function isNameTaken(string $name, ?OrganizationId $ignore = null): bool;

    /**
     * Parmi les noms candidats, ceux déjà pris — retournés normalisés (minuscules, trim).
     * Sert au dédoublonnage d'import en une seule requête.
     *
     * @param string[] $names
     *
     * @return string[]
     */
    public function takenNamesAmong(array $names): array;
}
