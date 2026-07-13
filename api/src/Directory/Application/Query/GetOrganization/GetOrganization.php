<?php

declare(strict_types=1);

namespace App\Directory\Application\Query\GetOrganization;

use App\Shared\Application\Query\Query;

final class GetOrganization implements Query
{
    public function __construct(public readonly string $id)
    {
    }
}
