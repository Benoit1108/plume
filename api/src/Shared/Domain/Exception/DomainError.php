<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Base de toutes les erreurs métier. Les sous-types (InvalidValue, NotFound,
 * Conflict) portent la sémantique ; l'Infrastructure les mappe vers HTTP
 * (422/404/409) via api_platform.exception_to_status.
 */
abstract class DomainError extends \DomainException
{
}
