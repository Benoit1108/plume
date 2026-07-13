<?php

declare(strict_types=1);

namespace App\Directory\Domain\Organization;

use App\Directory\Domain\Organization\Event\ContactAdded;
use App\Directory\Domain\Organization\Event\OrganizationCreated;
use App\Directory\Domain\Organization\Exception\ContactNotFound;
use App\Directory\Domain\Organization\Exception\DuplicateContactEmail;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Organisation (maison d'édition, labo A/V, agence…) — agrégat racine du Répertoire.
 * Contient ses Contact ; toute mutation d'un contact passe par la racine.
 */
final class Organization extends AggregateRoot
{
    /** @var Contact[] */
    private array $contacts = [];

    /**
     * @param LanguageCode[] $workingLanguages
     * @param Segment[]      $segments
     */
    private function __construct(
        private readonly OrganizationId $id,
        private readonly TenantId $tenantId,
        private string $name,
        private OrganizationType $type,
        private ?string $website,
        private ?CountryCode $country,
        private array $workingLanguages,
        private array $segments,
        private ?string $notes,
        private bool $doNotContact,
    ) {
    }

    /**
     * @param LanguageCode[] $workingLanguages
     * @param Segment[]      $segments
     */
    public static function create(
        OrganizationId $id,
        TenantId $tenantId,
        string $name,
        OrganizationType $type,
        \DateTimeImmutable $now,
        ?string $website = null,
        ?CountryCode $country = null,
        array $workingLanguages = [],
        array $segments = [],
        ?string $notes = null,
    ): self {
        $organization = new self(
            $id,
            $tenantId,
            self::guardName($name),
            $type,
            $website,
            $country,
            array_values($workingLanguages),
            array_values($segments),
            $notes,
            false,
        );
        $organization->recordEvent(new OrganizationCreated($id->toString(), $now));

        return $organization;
    }

    public function rename(string $name): void
    {
        $this->name = self::guardName($name);
    }

    /**
     * Met à jour le profil (remplace les champs éditables — cohérent avec un PATCH fusionné).
     *
     * @param LanguageCode[] $workingLanguages
     * @param Segment[]      $segments
     */
    public function updateProfile(
        string $name,
        OrganizationType $type,
        ?string $website,
        ?CountryCode $country,
        array $workingLanguages,
        array $segments,
        ?string $notes,
    ): void {
        $this->name = self::guardName($name);
        $this->type = $type;
        $this->website = $website;
        $this->country = $country;
        $this->workingLanguages = array_values($workingLanguages);
        $this->segments = array_values($segments);
        $this->notes = $notes;
    }

    public function addContact(Contact $contact, \DateTimeImmutable $now): void
    {
        $email = $contact->email();
        if (null !== $email && $this->hasContactWithEmail($email)) {
            throw DuplicateContactEmail::forEmail($email);
        }

        $this->contacts[] = $contact;
        $this->recordEvent(new ContactAdded($this->id->toString(), $contact->id()->toString(), $now));
    }

    public function updateContact(
        ContactId $contactId,
        string $fullName,
        ?string $role,
        ?EmailAddress $email,
        ?string $phone,
        ?string $linkedinUrl,
        ?LanguageCode $preferredLanguage,
    ): void {
        $existing = $this->contactWithId($contactId);
        if (null !== $email && $this->hasOtherContactWithEmail($contactId, $email)) {
            throw DuplicateContactEmail::forEmail($email);
        }

        // Remplace l'élément par une nouvelle instance (réf. différente) pour que Doctrine
        // détecte le changement dans la collection JSON.
        $replacement = new Contact($contactId, $fullName, $role, $email, $phone, $linkedinUrl, $preferredLanguage);
        if ($existing->doNotContact()) {
            $replacement->markDoNotContact();
        }

        $this->contacts = array_map(
            static fn (Contact $contact): Contact => $contact->id()->equals($contactId) ? $replacement : $contact,
            $this->contacts,
        );
    }

    public function removeContact(ContactId $contactId): void
    {
        $this->contacts = array_values(
            array_filter($this->contacts, static fn (Contact $contact): bool => !$contact->id()->equals($contactId)),
        );
    }

    private function contactWithId(ContactId $contactId): Contact
    {
        foreach ($this->contacts as $contact) {
            if ($contact->id()->equals($contactId)) {
                return $contact;
            }
        }

        throw ContactNotFound::withId($contactId);
    }

    private function hasOtherContactWithEmail(ContactId $contactId, EmailAddress $email): bool
    {
        foreach ($this->contacts as $contact) {
            if ($contact->id()->equals($contactId)) {
                continue;
            }
            $existing = $contact->email();
            if (null !== $existing && $existing->equals($email)) {
                return true;
            }
        }

        return false;
    }

    /** Marque l'organisation (et ses contacts) en « ne pas contacter » (RGPD). */
    public function markDoNotContact(): void
    {
        $this->doNotContact = true;
        foreach ($this->contacts as $contact) {
            $contact->markDoNotContact();
        }
    }

    private function hasContactWithEmail(EmailAddress $email): bool
    {
        foreach ($this->contacts as $contact) {
            $existing = $contact->email();
            if (null !== $existing && $existing->equals($email)) {
                return true;
            }
        }

        return false;
    }

    private static function guardName(string $name): string
    {
        $trimmed = trim($name);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException('Organization name cannot be empty.');
        }

        return $trimmed;
    }

    public function id(): OrganizationId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): OrganizationType
    {
        return $this->type;
    }

    public function doNotContact(): bool
    {
        return $this->doNotContact;
    }

    /** @return Contact[] */
    public function contacts(): array
    {
        return $this->contacts;
    }

    /** @return Segment[] */
    public function segments(): array
    {
        return $this->segments;
    }

    /** @return LanguageCode[] */
    public function workingLanguages(): array
    {
        return $this->workingLanguages;
    }

    public function website(): ?string
    {
        return $this->website;
    }

    public function country(): ?CountryCode
    {
        return $this->country;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }
}
