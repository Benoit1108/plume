<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\ListOrganizations;

use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Application\Query\QueryHandler;

final class ListOrganizationsHandler implements QueryHandler
{
    public function __construct(private readonly OrganizationRepository $organizations)
    {
    }

    /** @return Organization[] */
    public function __invoke(ListOrganizations $query): array
    {
        $type = null !== $query->type ? OrganizationType::tryFrom($query->type) : null;

        return $this->organizations->findMatching($type, $query->search);
    }
}
