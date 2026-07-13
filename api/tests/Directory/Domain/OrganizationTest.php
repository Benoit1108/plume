<?php

declare(strict_types=1);

namespace App\Tests\Directory\Domain;

use App\Directory\Domain\Organization\Contact;
use App\Directory\Domain\Organization\ContactId;
use App\Directory\Domain\Organization\Event\ContactAdded;
use App\Directory\Domain\Organization\Event\ContactRemoved;
use App\Directory\Domain\Organization\Event\ContactUpdated;
use App\Directory\Domain\Organization\Event\OrganizationCreated;
use App\Directory\Domain\Organization\Event\OrganizationDoNotContactCleared;
use App\Directory\Domain\Organization\Event\OrganizationDoNotContactMarked;
use App\Directory\Domain\Organization\Event\OrganizationProfileUpdated;
use App\Directory\Domain\Organization\Exception\ContactNotFound;
use App\Directory\Domain\Organization\Exception\DuplicateContactEmail;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/** Test de domaine pur (sans base ni conteneur). */
final class OrganizationTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-07-13 10:00:00');
    }

    private function anOrganization(): Organization
    {
        return Organization::create(
            OrganizationId::fromString('org-1'),
            TenantId::fromString('tenant-1'),
            'Actes Sud',
            OrganizationType::PUBLISHER,
            $this->now,
            segments: [Segment::PUBLISHING],
        );
    }

    private function aContact(string $id, ?string $email): Contact
    {
        return new Contact(
            ContactId::fromString($id),
            'Marie Dupont',
            'Responsable de collection',
            null !== $email ? EmailAddress::fromString($email) : null,
        );
    }

    public function testCreationTrimsNameAndRecordsEvent(): void
    {
        $org = Organization::create(
            OrganizationId::fromString('org-2'),
            TenantId::fromString('tenant-1'),
            '  Gallimard  ',
            OrganizationType::PUBLISHER,
            $this->now,
        );

        self::assertSame('Gallimard', $org->name());

        $events = $org->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizationCreated::class, $events[0]);
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidValue::class);

        Organization::create(
            OrganizationId::fromString('x'),
            TenantId::fromString('tenant-1'),
            '   ',
            OrganizationType::OTHER,
            $this->now,
        );
    }

    public function testUpdateProfileRecordsEvent(): void
    {
        $org = $this->anOrganization();
        $org->pullDomainEvents();

        $org->updateProfile('Actes Sud Littérature', OrganizationType::PUBLISHER, 'https://actes-sud.fr', null, [], [Segment::PUBLISHING], 'à relancer', $this->now);

        self::assertSame('Actes Sud Littérature', $org->name());
        self::assertSame('à relancer', $org->notes());
        self::assertInstanceOf(OrganizationProfileUpdated::class, $org->pullDomainEvents()[0]);
    }

    public function testAddContactRecordsEvent(): void
    {
        $org = $this->anOrganization();
        $org->pullDomainEvents();

        $org->addContact($this->aContact('c1', 'm.dupont@actes-sud.fr'), $this->now);

        self::assertCount(1, $org->contacts());
        self::assertInstanceOf(ContactAdded::class, $org->pullDomainEvents()[0]);
    }

    public function testDuplicateContactEmailIsRejectedCaseInsensitive(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'm.dupont@actes-sud.fr'), $this->now);

        $this->expectException(DuplicateContactEmail::class);
        $org->addContact($this->aContact('c2', 'M.Dupont@Actes-Sud.FR'), $this->now);
    }

    public function testUpdateContactRejectsEmailAlreadyUsedByAnotherContact(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'a@b.fr'), $this->now);
        $org->addContact(new Contact(ContactId::fromString('c2'), 'Jean Autre'), $this->now);

        $this->expectException(DuplicateContactEmail::class);
        $org->updateContact(ContactId::fromString('c2'), 'Jean Autre', null, EmailAddress::fromString('A@B.fr'), null, null, null, $this->now);
    }

    public function testMarkDoNotContactPropagatesToContactsAndRecordsEvent(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'a@b.fr'), $this->now);
        $org->pullDomainEvents();

        $org->markDoNotContact($this->now);

        self::assertTrue($org->doNotContact());
        self::assertTrue($org->contacts()[0]->doNotContact());
        self::assertInstanceOf(OrganizationDoNotContactMarked::class, $org->pullDomainEvents()[0]);
    }

    public function testAllowContactClearsFlagAndRecordsEvent(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'a@b.fr'), $this->now);
        $org->markDoNotContact($this->now);
        $org->pullDomainEvents();

        $org->allowContact($this->now);

        self::assertFalse($org->doNotContact());
        self::assertFalse($org->contacts()[0]->doNotContact());
        self::assertInstanceOf(OrganizationDoNotContactCleared::class, $org->pullDomainEvents()[0]);
    }

    public function testDoNotContactTransitionsAreIdempotent(): void
    {
        $org = $this->anOrganization();
        $org->pullDomainEvents();

        $org->allowContact($this->now); // déjà autorisée : aucun event
        self::assertCount(0, $org->pullDomainEvents());

        $org->markDoNotContact($this->now);
        $org->markDoNotContact($this->now); // déjà marquée : un seul event
        self::assertCount(1, $org->pullDomainEvents());
    }

    public function testUpdateContactReplacesDetailsAndRecordsEvent(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'a@b.fr'), $this->now);
        $org->pullDomainEvents();

        $org->updateContact(ContactId::fromString('c1'), 'Jean Nouveau', 'Éditeur', null, null, null, null, $this->now);

        self::assertSame('Jean Nouveau', $org->contacts()[0]->fullName());
        self::assertSame('Éditeur', $org->contacts()[0]->role());
        self::assertNull($org->contacts()[0]->email());
        self::assertInstanceOf(ContactUpdated::class, $org->pullDomainEvents()[0]);
    }

    public function testUpdateUnknownContactThrows(): void
    {
        $org = $this->anOrganization();

        $this->expectException(ContactNotFound::class);
        $org->updateContact(ContactId::fromString('nope'), 'X', null, null, null, null, null, $this->now);
    }

    public function testRemoveContactRecordsEvent(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'a@b.fr'), $this->now);
        $org->pullDomainEvents();

        $org->removeContact(ContactId::fromString('c1'), $this->now);

        self::assertCount(0, $org->contacts());
        self::assertInstanceOf(ContactRemoved::class, $org->pullDomainEvents()[0]);
    }

    public function testRemoveUnknownContactThrows(): void
    {
        $org = $this->anOrganization();

        $this->expectException(ContactNotFound::class);
        $org->removeContact(ContactId::fromString('nope'), $this->now);
    }
}
