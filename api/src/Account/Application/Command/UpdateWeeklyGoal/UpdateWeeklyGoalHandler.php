<?php

declare(strict_types=1);

namespace App\Account\Application\Command\UpdateWeeklyGoal;

use App\Account\Domain\Profile\Profile;
use App\Account\Domain\Profile\ProfileRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\ValueObject\TenantId;

final class UpdateWeeklyGoalHandler implements CommandHandler
{
    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly EventBus $eventBus,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(UpdateWeeklyGoal $command): void
    {
        $tenantId = TenantId::fromString($command->tenantId);
        $now = $this->clock->now();

        // Création paresseuse : le profil naît à la première personnalisation.
        $profile = $this->profiles->find($tenantId) ?? Profile::create($tenantId, $now);
        $profile->changeWeeklyGoal($command->weeklyGoal, $now);

        $this->profiles->save($profile);
        $this->eventBus->publish(...$profile->pullDomainEvents());
    }
}
