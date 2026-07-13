<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource;

/** DTO d'un contact (imbriqué dans OrganizationResource). */
final class ContactResource
{
    public ?string $id = null;
    public string $fullName = '';
    public ?string $role = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $linkedinUrl = null;
    public ?string $preferredLanguage = null;
    public bool $doNotContact = false;
}
