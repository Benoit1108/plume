<?php

declare(strict_types=1);

namespace App\Drafting\Application\ReadModel;

/** Vue de lecture d'un gabarit — immuable (ADR-0013). */
final class TemplateView
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $segment,
        public readonly string $language,
        public readonly ?string $subject,
        public readonly string $body,
    ) {
    }
}
