<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Directory\Domain\Organization\Exception\OrganizationNotFound;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;

/** Implémentation en mémoire du port — tests de la couche Application, sans base. */
final class InMemoryOrganizationRepository implements OrganizationRepository
{
    /** @var array<string, Organization> */
    private array $organizations = [];

    public function save(Organization $organization): void
    {
        $this->organizations[$organization->id()->toString()] = $organization;
    }

    public function get(OrganizationId $id): Organization
    {
        return $this->organizations[$id->toString()] ?? throw OrganizationNotFound::withId($id);
    }

    public function isNameTaken(string $name, ?OrganizationId $ignore = null): bool
    {
        $needle = mb_strtolower(trim($name));
        foreach ($this->organizations as $organization) {
            if (null !== $ignore && $organization->id()->equals($ignore)) {
                continue;
            }
            if (mb_strtolower(trim($organization->name())) === $needle) {
                return true;
            }
        }

        return false;
    }

    public function takenNamesAmong(array $names): array
    {
        $existing = array_map(
            static fn (Organization $organization): string => mb_strtolower(trim($organization->name())),
            array_values($this->organizations),
        );

        return array_values(array_intersect(
            array_map(static fn (string $name): string => mb_strtolower(trim($name)), $names),
            $existing,
        ));
    }

    /** @return Organization[] */
    public function all(): array
    {
        return array_values($this->organizations);
    }
}
