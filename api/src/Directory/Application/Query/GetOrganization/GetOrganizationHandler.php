<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\GetOrganization;

use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Shared\Application\Query\QueryHandler;

final class GetOrganizationHandler implements QueryHandler
{
    public function __construct(private readonly OrganizationRepository $organizations)
    {
    }

    public function __invoke(GetOrganization $query): Organization
    {
        return $this->organizations->get(OrganizationId::fromString($query->id));
    }
}
