<?php

declare(strict_types=1);

namespace App\Account\Application\Command\UpdateProfile;

use App\Account\Domain\Profile\Profile;
use App\Account\Domain\Profile\ProfileRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\ValueObject\TenantId;

final class UpdateProfileHandler implements CommandHandler
{
    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(UpdateProfile $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $now = $this->clock->now();

        // Création paresseuse : le profil naît à la première personnalisation.
        $profile = $this->profiles->find($tenantId) ?? Profile::create($tenantId, $now);
        $profile->changeWeeklyGoal($command->weeklyGoal, $now);
        $profile->changePresentation($command->bio, $command->specialties, $command->signature, $now);

        $this->profiles->save($profile);
        $this->eventBus->publish(...$profile->pullDomainEvents());
    }
}
