<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Source;

/**
 * Source d'annonces (Strategy). Une implémentation par canal — M3.1a : RSS (réel) et
 * un faux déterministe (démo, sans réseau). Le parsing est best-effort : un item
 * malformé est ignoré, jamais propagé en exception.
 */
interface AlertSource
{
    /** @return iterable<ParsedAlert> */
    public function fetch(): iterable;
}
