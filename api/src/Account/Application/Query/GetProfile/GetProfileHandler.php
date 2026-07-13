<?php

declare(strict_types=1);

namespace App\Account\Application\Query\GetProfile;

use App\Account\Application\ReadModel\ProfileSettings;
use App\Account\Application\ReadModel\ProfileView;
use App\Shared\Application\Query\QueryHandler;

final class GetProfileHandler implements QueryHandler
{
    public function __construct(private readonly ProfileSettings $settings)
    {
    }

    public function __invoke(GetProfile $query): ProfileView
    {
        return $this->settings->current();
    }
}
