<?php

declare(strict_types=1);

namespace App\Drafting\Application\Exception;

/** Le générateur n'a pas pu produire de message (réseau, quota, réponse invalide). */
final class GenerationFailed extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
