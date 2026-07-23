<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Identifiant-valeur persisté sous forme de chaîne (UUID en pratique). Contrat commun aux VOs
 * d'ID des agrégats — permet un type DBAL générique unique (AbstractStringIdType) plutôt qu'un
 * type quasi identique par agrégat (dette ADR-0022 §2).
 */
interface StringId extends \Stringable
{
    public static function fromString(string $value): self;

    public function toString(): string;
}
