<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/** Ressource métier introuvable (dans le périmètre du tenant) → HTTP 404. */
abstract class NotFound extends DomainError
{
}
