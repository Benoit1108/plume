<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Source;

use App\Sourcing\Application\Source\AlertSource;

/**
 * Source de repli NEUTRE (prod) : ne renvoie rien. En prod, un tenant sans flux configuré qui
 * déclenche une relève ne doit PAS voir apparaître les annonces de démonstration (pollution de
 * données d'un vrai compte, revue pré-V2). La démo (`FakeAlertSource`) reste le repli en dev/E2E.
 */
final class NullAlertSource implements AlertSource
{
    public function fetch(string $feedUrl): iterable
    {
        return [];
    }
}
