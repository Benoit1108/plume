<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Consumer;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\LeadContext;
use App\Drafting\Domain\Draft\Event\DraftRequested;
use Doctrine\DBAL\Connection;

/**
 * Assemble la matière première d'une génération : contexte de la piste
 * (fourni), présentation du profil et gabarit éventuel (SQL direct, tenant
 * EXPLICITE — le builder tourne dans le worker, sans contexte de requête).
 */
final class DraftPromptBuilder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function build(DraftRequested $event, LeadContext $context): DraftPrompt
    {
        $profile = $this->connection->fetchAssociative(
            'SELECT bio, specialties, signature FROM profile WHERE tenant_id = :tenant',
            ['tenant' => $event->tenantId],
        );

        $template = null;
        if (null !== $event->templateId) {
            // Gabarit supprimé entre-temps : on dégrade vers le squelette par défaut.
            $template = $this->connection->fetchAssociative(
                'SELECT subject, body FROM template WHERE tenant_id = :tenant AND id = :id',
                ['tenant' => $event->tenantId, 'id' => $event->templateId],
            ) ?: null;
        }

        return new DraftPrompt(
            type: $event->type,
            targetLanguage: $event->targetLanguage,
            languagePair: $context->languagePair,
            leadStatus: $context->status,
            organizationName: $context->organizationName,
            segment: $context->segment,
            contactName: $context->contactName,
            bio: $this->text($profile ?: null, 'bio'),
            specialties: $this->text($profile ?: null, 'specialties'),
            signature: $this->text($profile ?: null, 'signature'),
            templateSubject: $this->text($template, 'subject'),
            templateBody: $this->text($template, 'body'),
        );
    }

    /** @param array<string, mixed>|null $row */
    private function text(?array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== trim($value) ? $value : null;
    }
}
