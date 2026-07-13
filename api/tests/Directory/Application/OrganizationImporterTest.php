<?php

declare(strict_types=1);

namespace App\Tests\Directory\Application;

use App\Directory\Application\Command\AddContact\AddContact;
use App\Directory\Application\Command\AddContact\AddContactHandler;
use App\Directory\Application\Command\CreateOrganization\CreateOrganization;
use App\Directory\Application\Command\CreateOrganization\CreateOrganizationHandler;
use App\Directory\Application\Import\ImportedOrganizationRow;
use App\Directory\Application\Import\OrganizationImporter;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Application\Command\Command;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FixedClock;
use App\Tests\Support\HandlerMapCommandBus;
use App\Tests\Support\InMemoryOrganizationRepository;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

/** Test d'application de l'orchestration d'import (bus synchrone + repo in-memory). */
final class OrganizationImporterTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private OrganizationImporter $importer;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $eventBus = new RecordingEventBus();
        $clock = new FixedClock(new \DateTimeImmutable('2026-07-13 10:00:00'));

        $createHandler = new CreateOrganizationHandler($this->organizations, $eventBus, $clock);
        $addContactHandler = new AddContactHandler($this->organizations, $eventBus, $clock);
        $commandBus = new HandlerMapCommandBus([
            CreateOrganization::class => static function (Command $command) use ($createHandler): void {
                \assert($command instanceof CreateOrganization);
                $createHandler($command);
            },
            AddContact::class => static function (Command $command) use ($addContactHandler): void {
                \assert($command instanceof AddContact);
                $addContactHandler($command);
            },
        ]);

        $this->importer = new OrganizationImporter($commandBus, $this->organizations, new SequentialIdGenerator());
    }

    private function aRow(int $line, string $name, ?string $contactName = null, ?string $contactEmail = null): ImportedOrganizationRow
    {
        return new ImportedOrganizationRow($line, $name, 'PUBLISHER', null, 'FR', ['fr'], ['PUBLISHING'], null, $contactName, null, $contactEmail, null);
    }

    public function testImportsRowsWithContactAndCounts(): void
    {
        $report = $this->importer->import('tenant-1', [
            $this->aRow(2, 'Actes Sud', 'Claire Martin', 'claire@actes-sud.fr'),
            $this->aRow(3, 'Gallimard'),
        ]);

        self::assertSame(2, $report->imported);
        self::assertSame(0, $report->skipped);
        self::assertSame(0, $report->failed);
        self::assertCount(2, $this->organizations->all());

        $withContact = $this->organizations->all()[0];
        self::assertSame('Actes Sud', $withContact->name());
        self::assertCount(1, $withContact->contacts());
        self::assertSame('Claire Martin', $withContact->contacts()[0]->fullName());
    }

    public function testSkipsNamesAlreadyInBaseAndInBatch(): void
    {
        $existing = Organization::create(
            OrganizationId::fromString('org-0'),
            TenantId::fromString('tenant-1'),
            'Actes Sud',
            OrganizationType::PUBLISHER,
            new \DateTimeImmutable('2026-07-13 09:00:00'),
        );
        $existing->pullDomainEvents();
        $this->organizations->save($existing);

        $report = $this->importer->import('tenant-1', [
            $this->aRow(2, 'ACTES SUD'),      // déjà en base (insensible à la casse)
            $this->aRow(3, 'Gallimard'),
            $this->aRow(4, ' gallimard '),    // doublon dans le lot
        ]);

        self::assertSame(1, $report->imported);
        self::assertSame(2, $report->skipped);
        self::assertCount(2, $this->organizations->all());
    }

    public function testParseErrorsAreCountedAsFailed(): void
    {
        $report = $this->importer->import('tenant-1', [
            $this->aRow(3, 'Gallimard'),
        ], [
            ['line' => 2, 'message' => 'Nom manquant, ligne ignorée.'],
        ]);

        self::assertSame(1, $report->imported);
        self::assertSame(1, $report->failed);
        self::assertCount(1, $report->errors);
        self::assertSame(2, $report->errors[0]['line']);
    }

    public function testDomainErrorOnContactIsReportedWithoutFailingTheRow(): void
    {
        $report = $this->importer->import('tenant-1', [
            $this->aRow(2, 'Actes Sud', 'Claire Martin', 'not-an-email'),
        ]);

        self::assertSame(1, $report->imported);
        self::assertSame(0, $report->failed);
        self::assertCount(1, $report->errors);
        self::assertStringContainsString('contact ignoré', $report->errors[0]['message']);
        self::assertCount(0, $this->organizations->all()[0]->contacts());
    }
}
