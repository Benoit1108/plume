<?php

declare(strict_types=1);

namespace App;

use App\Account\Infrastructure\Scheduler\PurgeDeletedAccountsTick;
use App\Account\Infrastructure\Scheduler\PurgeExpiredDemosTick;
use App\Mailbox\Infrastructure\Scheduler\FetchAllAlertEmailsTick;
use App\Mailbox\Infrastructure\Scheduler\FetchAllRepliesTick;
use App\Notification\Infrastructure\Scheduler\NotifyDormantClientsTick;
use App\Notification\Infrastructure\Scheduler\NotifyDueFollowUpsTick;
use App\Notification\Infrastructure\Scheduler\PurgeOldNotificationsTick;
use App\Notification\Infrastructure\Scheduler\SendNotificationDigestsTick;
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

            // Relève des alertes email (label dédié) de toutes les boîtes connectées (M3.2).
            ->add(RecurringMessage::every('30 minutes', new FetchAllAlertEmailsTick()))

            // Purge quotidienne du brut des annonces rejetées de longue date (D6).
            ->add(RecurringMessage::every('1 day', new PurgeRawAlertsTick()))

            // Purge quotidienne des comptes en soft-delete au-delà du délai de grâce (RGPD, V2.0-a2).
            ->add(RecurringMessage::every('1 day', new PurgeDeletedAccountsTick()))

            // Purge horaire des comptes de démo éphémères expirés (vitrine V2).
            ->add(RecurringMessage::every('1 hour', new PurgeExpiredDemosTick()))

            // Notifications « relance due aujourd'hui » — horaire (passage de minuit par fuseau),
            // idempotent (une notification par relance et par échéance).
            ->add(RecurringMessage::every('1 hour', new NotifyDueFollowUpsTick()))

            // Rappel des clients gagnés dormants à réactiver — quotidien (au plus 1/mois/client, V2.4).
            ->add(RecurringMessage::every('1 day', new NotifyDormantClientsTick()))

            // Rétention du centre de notifications (lues > 90 j) + jetons de reset expirés (revue globale).
            ->add(RecurringMessage::every('1 day', new PurgeOldNotificationsTick()))

            // Digest email des notifications non lues, par tenant selon sa préférence (V2).
            // Quotidien : DAILY chaque jour (fenêtre 24 h), WEEKLY le lundi (fenêtre 7 j).
            ->add(RecurringMessage::every('1 day', new SendNotificationDigestsTick()));
    }
}
