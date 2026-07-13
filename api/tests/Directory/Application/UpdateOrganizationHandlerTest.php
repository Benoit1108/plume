<?php

declare(strict_types=1);

namespace App\Tests\Directory\Application;

use App\Directory\Application\Command\UpdateOrganization\UpdateOrganization;
use App\Directory\Application\Command\UpdateOrganization\UpdateOrganizationHandler;
use App\Directory\Domain\Organization\Event\OrganizationDoNotContactCleared;
use App\Directory\Domain\Organization\Event\OrganizationDoNotContactMarked;
use App\Directory\Domain\Organization\Event\OrganizationProfileUpdated;
use App\Directory\Domain\Organization\Exception\OrganizationNameAlreadyUsed;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryOrganizationRepository;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

/** Test d'application : repo in-memory, sans base ni conteneur. */
final class UpdateOrganizationHandlerTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private RecordingEventBus $eventBus;
    private UpdateOrganizationHandler $handler;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new UpdateOrganizationHandler(
            $this->organizations,
            $this->eventBus,
            new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00')),
        );
    }

    private function seed(string $id, string $name): void
    {
        $organization = Organization::create(
            OrganizationId::fromString($id),
            TenantId::fromString('tenant-1'),
            $name,
            OrganizationType::PUBLISHER,
            new \DateTimeImmutable('2026-07-13 09:00:00'),
        );
        $organization->pullDomainEvents();
        $this->organizations->save($organization);
    }

    private function aCommand(string $name, bool $doNotContact = false): UpdateOrganization
    {
        return new UpdateOrganization('org-1', $name, 'AGENCY', null, null, [], [], null, $doNotContact);
    }

    public function testUpdatesProfileAndPublishesEvent(): void
    {
        $this->seed('org-1', 'Actes Sud');

        ($this->handler)($this->aCommand('Actes Sud Littérature'));

        $organization = $this->organizations->get(OrganizationId::fromString('org-1'));
        self::assertSame('Actes Sud Littérature', $organization->name());
        self::assertSame(OrganizationType::AGENCY, $organization->type());
        self::assertSame(1, $this->eventBus->countOf(OrganizationProfileUpdated::class));
    }

    public function testKeepingOwnNameIsAllowed(): void
    {
        $this->seed('org-1', 'Actes Sud');

        ($this->handler)($this->aCommand('Actes Sud'));

        self::assertSame('Actes Sud', $this->organizations->get(OrganizationId::fromString('org-1'))->name());
    }

    public function testRejectsNameTakenByAnotherOrganization(): void
    {
        $this->seed('org-1', 'Actes Sud');
        $this->seed('org-2', 'Gallimard');

        $this->expectException(OrganizationNameAlreadyUsed::class);
        ($this->handler)($this->aCommand('gallimard'));
    }

    public function testDoNotContactRoundTripPublishesBothEvents(): void
    {
        $this->seed('org-1', 'Actes Sud');

        ($this->handler)($this->aCommand('Actes Sud', doNotContact: true));
        self::assertTrue($this->organizations->get(OrganizationId::fromString('org-1'))->doNotContact());
        self::assertSame(1, $this->eventBus->countOf(OrganizationDoNotContactMarked::class));

        ($this->handler)($this->aCommand('Actes Sud', doNotContact: false));
        self::assertFalse($this->organizations->get(OrganizationId::fromString('org-1'))->doNotContact());
        self::assertSame(1, $this->eventBus->countOf(OrganizationDoNotContactCleared::class));
    }
}
