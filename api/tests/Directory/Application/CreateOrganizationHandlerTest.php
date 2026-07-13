<?php

declare(strict_types=1);

namespace App\Tests\Directory\Application;

use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Directory\Application\Command\CreateOrganization\CreateOrganizationHandler;
use App\Directory\Domain\Organization\Event\OrganizationCreated;
use App\Directory\Domain\Organization\Exception\OrganizationNameAlreadyUsed;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Domain\Exception\InvalidValue;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryOrganizationRepository;
use App\Tests\Support\RecordingEventBus;
use PHPUnit\Framework\TestCase;

/** Test d'application : repo in-memory, sans base ni conteneur. */
final class CreateOrganizationHandlerTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private RecordingEventBus $eventBus;
    private CreateOrganizationHandler $handler;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->eventBus = new RecordingEventBus();
        $this->handler = new CreateOrganizationHandler(
            $this->organizations,
            $this->eventBus,
            new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00')),
        );
    }

    private function aCommand(string $name = 'Actes Sud'): CreateOrganization
    {
        return new CreateOrganization('org-1', 'tenant-1', $name, 'PUBLISHER', 'https://example.fr', 'FR', ['en', 'fr'], ['PUBLISHING'], 'notes');
    }

    public function testCreatesAndPublishesEvent(): void
    {
        ($this->handler)($this->aCommand());

        $organization = $this->organizations->get(OrganizationId::fromString('org-1'));
        self::assertSame('Actes Sud', $organization->name());
        self::assertSame(OrganizationType::PUBLISHER, $organization->type());
        self::assertSame('FR', $organization->country()?->toString());
        self::assertCount(2, $organization->workingLanguages());
        self::assertSame(1, $this->eventBus->countOf(OrganizationCreated::class));
    }

    public function testRejectsNameAlreadyUsedCaseInsensitive(): void
    {
        ($this->handler)($this->aCommand('Actes Sud'));

        $this->expectException(OrganizationNameAlreadyUsed::class);
        ($this->handler)(new CreateOrganization('org-2', 'tenant-1', '  actes SUD ', 'OTHER', null, null, [], [], null));
    }

    public function testRejectsUnknownType(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->handler)(new CreateOrganization('org-1', 'tenant-1', 'X', 'BANANA', null, null, [], [], null));
    }

    public function testRejectsUnknownSegment(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->handler)(new CreateOrganization('org-1', 'tenant-1', 'X', 'OTHER', null, null, [], ['CUISINE'], null));
    }
}
