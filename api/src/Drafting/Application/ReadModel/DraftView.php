<?php

declare(strict_types=1);

namespace App\Drafting\Application\ReadModel;

/** Vue de lecture d'un brouillon — immuable (ADR-0013). */
final class DraftView
{
    public function __construct(
        public readonly string $id,
        public readonly string $leadId,
        public readonly string $type,
        public readonly string $targetLanguage,
        public readonly ?string $templateId,
        public readonly ?string $subject,
        public readonly string $body,
        public readonly string $status,
        public readonly ?string $failureReason,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }
}
