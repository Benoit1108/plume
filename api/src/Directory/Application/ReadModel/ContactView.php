<?php

declare(strict_types=1);

namespace App\Directory\Application\ReadModel;

/** Vue de lecture d'un contact — immuable, découplée de l'agrégat. */
final class ContactView
{
    public function __construct(
        public readonly string $id,
        public readonly string $fullName,
        public readonly ?string $role,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $linkedinUrl,
        public readonly ?string $preferredLanguage,
        public readonly bool $doNotContact,
    ) {
    }
}
