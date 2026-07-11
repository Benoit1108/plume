<?php

declare(strict_types=1);

namespace App\Shared\Application\Query;

/**
 * Marqueur des handlers de requête. Enregistré comme messenger.message_handler
 * (bus query.bus) via `_instanceof`.
 */
interface QueryHandler
{
}
