<?php

declare(strict_types=1);

namespace App;

use App\Mailbox\Infrastructure\Scheduler\FetchAllRepliesTick;
use App\Sourcing\Infrastructure\Scheduler\PollAllSourcesTick;
use App\Sourcing\Infrastructure\Scheduler\PurgeRawAlertsTick;
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
            ->add(RecurringMessage::every('1 day', new RunCommandMessage('gesdinet:jwt:clear')))

            // Relève des réponses (D2 : polling — push réévalué à l'hébergement prod, ADR-0017).
            ->add(RecurringMessage::every('5 minutes', new FetchAllRepliesTick()))

            // Relève des flux d'annonces (RSS) de tous les tenants ayant un flux actif (M3.1b).
            ->add(RecurringMessage::every('30 minutes', new PollAllSourcesTick()))

            // Purge quotidienne du brut des annonces rejetées de longue date (D6).
            ->add(RecurringMessage::every('1 day', new PurgeRawAlertsTick()));
    }
}
