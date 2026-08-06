<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Mail;

use Doctrine\DBAL\Connection;

/**
 * Registre des emails périodiques déjà expédiés (revue BACK-P2a).
 *
 * Les ticks « bilan hebdomadaire » et « digest » parcourent des destinataires et envoient. Sur un
 * transport qui rejoue (Messenger, `max_retries: 3`), un échec au dixième destinataire réexpédiait
 * l'email aux neuf premiers ; le scheduler `stateful` peut aussi rejouer une occurrence manquée.
 * Les notifications de relance et de client dormant portaient déjà une clé d'idempotence — c'était
 * l'exception, elle disparaît.
 *
 * `claim()` réserve une clé de façon ATOMIQUE : le premier passage l'obtient, les suivants non.
 * La clé ne porte AUCUNE donnée personnelle (identifiant de tenant + période), et la table vit
 * hors tenant : c'est de l'état de maintenance globale, écrit par le scheduler propriétaire.
 */
final class EmailDispatchLedger
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Vrai si l'envoi est à faire MAINTENANT (clé réservée) ; faux s'il a déjà été fait. */
    public function claim(string $key): bool
    {
        return 1 === $this->connection->executeStatement(
            'INSERT INTO email_dispatch (dispatch_key) VALUES (:key) ON CONFLICT (dispatch_key) DO NOTHING',
            ['key' => $key],
        );
    }

    /** Clé d'un bilan hebdomadaire : un par tenant et par semaine ISO. */
    public static function weeklyReportKey(string $tenantId, \DateTimeImmutable $on): string
    {
        return \sprintf('weekly-report:%s:%s', $tenantId, $on->format('o-\WW'));
    }

    /** Clé d'un digest : un par tenant, par fréquence et par jour (DAILY) ou semaine ISO (WEEKLY). */
    public static function digestKey(string $tenantId, string $frequency, \DateTimeImmutable $on): string
    {
        $period = 'WEEKLY' === $frequency ? $on->format('o-\WW') : $on->format('Y-m-d');

        return \sprintf('digest:%s:%s:%s', strtolower($frequency), $tenantId, $period);
    }
}
