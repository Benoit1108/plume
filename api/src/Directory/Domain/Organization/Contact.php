<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\LanguageCode;

/**
 * Contact — entité DANS l'agrégat Organization. Les mutations passent par la racine.
 */
final class Contact
{
    private bool $doNotContact = false;

    public function __construct(
        private readonly ContactId $id,
        private string $fullName,
        private ?string $role = null,
        private ?EmailAddress $email = null,
        private ?string $phone = null,
        private ?string $linkedinUrl = null,
        private ?LanguageCode $preferredLanguage = null,
    ) {
        $this->fullName = self::guardName($fullName);
    }

    private static function guardName(string $name): string
    {
        $trimmed = trim($name);
        if ('' === $trimmed) {
            throw InvalidValue::because('Contact full name cannot be empty.');
        }

        return $trimmed;
    }

    public function id(): ContactId
    {
        return $this->id;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function email(): ?EmailAddress
    {
        return $this->email;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function preferredLanguage(): ?LanguageCode
    {
        return $this->preferredLanguage;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function linkedinUrl(): ?string
    {
        return $this->linkedinUrl;
    }

    public function doNotContact(): bool
    {
        return $this->doNotContact;
    }

    public function markDoNotContact(): void
    {
        $this->doNotContact = true;
    }

    public function allowContact(): void
    {
        $this->doNotContact = false;
    }
}
