<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\CreateOrganization;

use App\Directory\Domain\Organization\Exception\OrganizationNameAlreadyUsed;
use App\Directory\Domain\Organization\Organization;
use App\Directory\Domain\Organization\OrganizationId;
use App\Directory\Domain\Organization\OrganizationRepository;
use App\Directory\Domain\Organization\OrganizationType;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

final class CreateOrganizationHandler implements CommandHandler
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateOrganization $command): void
    {
        // Unicité du nom par tenant (insensible à la casse) — l'index unique en base
        // sert de filet contre les insertions concurrentes.
        if ($this->organizations->isNameTaken($command->name)) {
            throw OrganizationNameAlreadyUsed::named($command->name);
        }

        $organization = Organization::create(
            OrganizationId::fromString($command->id),
            TenantId::fromString($command->tenantId),
            $command->name,
            OrganizationType::tryFrom($command->type) ?? throw InvalidValue::because(sprintf('Unknown organization type "%s".', $command->type)),
            $this->clock->now(),
            website: $command->website,
            country: null !== $command->country ? CountryCode::fromString($command->country) : null,
            workingLanguages: array_map(static fn (string $code): LanguageCode => LanguageCode::fromString($code), $command->workingLanguages),
            segments: array_map(static fn (string $segment): Segment => Segment::tryFrom($segment) ?? throw InvalidValue::because(sprintf('Unknown segment "%s".', $segment)), $command->segments),
            notes: $command->notes,
        );

        $this->organizations->save($organization);
        $this->eventBus->publish(...$organization->pullDomainEvents());
    }
}
