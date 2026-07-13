<?php

declare(strict_types=1);

namespace App\Tests\Directory\Application;

use App\Directory\Application\Command\AddContact\AddContact;
use App\Directory\Application\Command\AddContact\AddContactHandler;
use App\Directory\Application\Command\RemoveContact\RemoveContact;
use App\Directory\Application\Command\RemoveContact\RemoveContactHandler;
use App\Directory\Application\Command\UpdateContact\UpdateContact;
use App\Directory\Application\Command\UpdateContact\UpdateContactHandler;
use App\Directory\Domain\Organization\Event\ContactAdded;
use App\Directory\Domain\Organization\Event\ContactRemoved;
use App\Directory\Domain\Organization\Event\ContactUpdated;
use App\Directory\Domain\Organization\Exception\ContactNotFound;
use App\Directory\Domain\Organization\Exception\DuplicateContactEmail;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryOrganizationRepository;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

/** Tests d'application des handlers de contacts : repo in-memory, sans base. */
final class ContactHandlersTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private RecordingEventBus $eventBus;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->eventBus = new RecordingEventBus();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00'));

        $organization = Organization::create(
            OrganizationId::fromString('org-1'),
            TenantId::fromString('tenant-1'),
            'Actes Sud',
            OrganizationType::PUBLISHER,
            $this->clock->now(),
        );
        $organization->pullDomainEvents();
        $this->organizations->save($organization);
    }

    private function addContact(string $contactId, ?string $email): void
    {
        (new AddContactHandler($this->organizations, $this->eventBus, $this->clock))(
            new AddContact('org-1', $contactId, 'Marie Dupont', 'Éditrice', $email, null, null, 'fr'),
        );
    }

    public function testAddContactPersistsAndPublishes(): void
    {
        $this->addContact('c1', 'marie@actes-sud.fr');

        $contacts = $this->organizations->get(OrganizationId::fromString('org-1'))->contacts();
        self::assertCount(1, $contacts);
        self::assertSame('Marie Dupont', $contacts[0]->fullName());
        self::assertSame('fr', $contacts[0]->preferredLanguage()?->toString());
        self::assertSame(1, $this->eventBus->countOf(ContactAdded::class));
    }

    public function testAddContactRejectsDuplicateEmail(): void
    {
        $this->addContact('c1', 'marie@actes-sud.fr');

        $this->expectException(DuplicateContactEmail::class);
        (new AddContactHandler($this->organizations, $this->eventBus, $this->clock))(
            new AddContact('org-1', 'c2', 'Autre Personne', null, 'MARIE@actes-sud.fr', null, null, null),
        );
    }

    public function testUpdateContactReplacesDetailsAndPublishes(): void
    {
        $this->addContact('c1', 'marie@actes-sud.fr');

        (new UpdateContactHandler($this->organizations, $this->eventBus, $this->clock))(
            new UpdateContact('org-1', 'c1', 'Marie Durand', 'Directrice', 'marie@actes-sud.fr', '0102030405', null, 'en'),
        );

        $contact = $this->organizations->get(OrganizationId::fromString('org-1'))->contacts()[0];
        self::assertSame('Marie Durand', $contact->fullName());
        self::assertSame('0102030405', $contact->phone());
        self::assertSame('en', $contact->preferredLanguage()?->toString());
        self::assertSame(1, $this->eventBus->countOf(ContactUpdated::class));
    }

    public function testRemoveContactPublishes(): void
    {
        $this->addContact('c1', 'marie@actes-sud.fr');

        (new RemoveContactHandler($this->organizations, $this->eventBus, $this->clock))(
            new RemoveContact('org-1', 'c1'),
        );

        self::assertCount(0, $this->organizations->get(OrganizationId::fromString('org-1'))->contacts());
        self::assertSame(1, $this->eventBus->countOf(ContactRemoved::class));
    }

    public function testRemoveUnknownContactThrows(): void
    {
        $this->expectException(ContactNotFound::class);
        (new RemoveContactHandler($this->organizations, $this->eventBus, $this->clock))(
            new RemoveContact('org-1', 'nope'),
        );
    }
}
