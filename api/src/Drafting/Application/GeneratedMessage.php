<?php

declare(strict_types=1);

namespace App\Drafting\Application;

/** Résultat d'une génération : sujet optionnel + corps. */
final class GeneratedMessage
{
    public function __construct(
        public readonly ?string $subject,
        public readonly string $body,
    ) {
    }
}
