<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // rejoue les tâches manquées
            ->processOnlyLastMissedRun(true) // mais seulement la dernière occurrence

            // Purge quotidienne des refresh tokens expirés (la table grossit sinon).
            ->add(RecurringMessage::every('1 day', new RunCommandMessage('gesdinet:jwt:clear')));
    }
}
