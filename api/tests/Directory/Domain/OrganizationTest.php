<?php

declare(strict_types=1);

namespace App\Tests\Directory\Domain;

use App\Directory\Domain\Organization\Contact;
use App\Directory\Domain\Organization\ContactId;
use App\Directory\Domain\Organization\Event\ContactAdded;
use App\Directory\Domain\Organization\Event\OrganizationCreated;
use App\Directory\Domain\Organization\Exception\DuplicateContactEmail;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/** Test de domaine pur (sans base ni conteneur). */
final class OrganizationTest extends TestCase
{
    private function anOrganization(): Organization
    {
        return Organization::create(
            OrganizationId::fromString('org-1'),
            TenantId::fromString('tenant-1'),
            'Actes Sud',
            OrganizationType::PUBLISHER,
            new \DateTimeImmutable('2026-07-13 10:00:00'),
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
            new \DateTimeImmutable(),
        );

        self::assertSame('Gallimard', $org->name());

        $events = $org->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizationCreated::class, $events[0]);
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Organization::create(
            OrganizationId::fromString('x'),
            TenantId::fromString('tenant-1'),
            '   ',
            OrganizationType::OTHER,
            new \DateTimeImmutable(),
        );
    }

    public function testAddContactRecordsEvent(): void
    {
        $org = $this->anOrganization();
        $org->pullDomainEvents();

        $org->addContact($this->aContact('c1', 'm.dupont@actes-sud.fr'), new \DateTimeImmutable());

        self::assertCount(1, $org->contacts());
        self::assertInstanceOf(ContactAdded::class, $org->pullDomainEvents()[0]);
    }

    public function testDuplicateContactEmailIsRejectedCaseInsensitive(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'm.dupont@actes-sud.fr'), new \DateTimeImmutable());

        $this->expectException(DuplicateContactEmail::class);
        $org->addContact($this->aContact('c2', 'M.Dupont@Actes-Sud.FR'), new \DateTimeImmutable());
    }

    public function testMarkDoNotContactPropagatesToContacts(): void
    {
        $org = $this->anOrganization();
        $org->addContact($this->aContact('c1', 'a@b.fr'), new \DateTimeImmutable());

        $org->markDoNotContact();

        self::assertTrue($org->doNotContact());
        self::assertTrue($org->contacts()[0]->doNotContact());
    }
}
