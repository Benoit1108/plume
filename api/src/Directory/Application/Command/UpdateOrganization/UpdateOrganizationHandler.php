<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\UpdateOrganization;

use App\Directory\Domain\Organization\Exception\OrganizationNameAlreadyUsed;
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

final class UpdateOrganizationHandler implements CommandHandler
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(UpdateOrganization $command): void
    {
        $id = OrganizationId::fromString($command->id);
        $organization = $this->organizations->get($id);

        if ($this->organizations->isNameTaken($command->name, ignore: $id)) {
            throw OrganizationNameAlreadyUsed::named($command->name);
        }

        $now = $this->clock->now();
        $organization->updateProfile(
            $command->name,
            OrganizationType::tryFrom($command->type) ?? throw InvalidValue::because(sprintf('Unknown organization type "%s".', $command->type)),
            $command->website,
            null !== $command->country ? CountryCode::fromString($command->country) : null,
            array_map(static fn (string $code): LanguageCode => LanguageCode::fromString($code), $command->workingLanguages),
            array_map(static fn (string $segment): Segment => Segment::tryFrom($segment) ?? throw InvalidValue::because(sprintf('Unknown segment "%s".', $segment)), $command->segments),
            $command->notes,
            $now,
        );

        // Bascule « ne pas contacter » réversible — chaque changement émet un event (traçabilité RGPD).
        if ($command->doNotContact !== $organization->doNotContact()) {
            $command->doNotContact ? $organization->markDoNotContact($now) : $organization->allowContact($now);
        }

        $this->organizations->save($organization);
        $this->eventBus->publish(...$organization->pullDomainEvents());
    }
}
