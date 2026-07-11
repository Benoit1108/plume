<?php

declare(strict_types=1);

namespace App\Shared\Application\Command;

/**
 * Marqueur des handlers de commande. Enregistré comme messenger.message_handler
 * (bus command.bus) via `_instanceof` — évite un attribut framework dans la couche Application.
 */
interface CommandHandler
{
}
