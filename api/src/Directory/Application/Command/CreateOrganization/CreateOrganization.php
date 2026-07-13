<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\CreateOrganization;

use App\Shared\Application\Command\Command;

final class CreateOrganization implements Command
{
    /**
     * @param string[] $workingLanguages
     * @param string[] $segments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $website,
        public readonly ?string $country,
        public readonly array $workingLanguages,
        public readonly array $segments,
        public readonly ?string $notes,
    ) {
    }
}
