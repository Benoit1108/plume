<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\UpdateContact;

use App\Shared\Application\Command\Command;

final class UpdateContact implements Command
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $contactId,
        public readonly string $fullName,
        public readonly ?string $role,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $linkedinUrl,
        public readonly ?string $preferredLanguage,
    ) {
    }
}
