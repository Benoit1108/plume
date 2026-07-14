<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Consumer;

use App\Drafting\Application\DraftPrompt;
use App\Drafting\Application\LeadContext;
use App\Drafting\Domain\Draft\Event\DraftRequested;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;
use Doctrine\DBAL\Connection;

/**
 * Assemble la matière première d'une génération : contexte de la piste
 * (fourni), présentation du profil et gabarit éventuel (SQL direct, tenant
 * EXPLICITE — le builder tourne dans le worker, sans contexte de requête).
 */
final class DraftPromptBuilder
{
    use HydratesRows;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function build(DraftRequested $event, LeadContext $context): DraftPrompt
    {
        $profile = $this->connection->fetchAssociative(
            'SELECT bio, specialties, signature FROM profile WHERE tenant_id = :tenant',
            ['tenant' => $event->tenantId],
        );

        $template = [];
        if (null !== $event->templateId) {
            // Gabarit supprimé entre-temps : on dégrade vers le squelette par défaut.
            $template = $this->connection->fetchAssociative(
                'SELECT subject, body FROM template WHERE tenant_id = :tenant AND id = :id',
                ['tenant' => $event->tenantId, 'id' => $event->templateId],
            ) ?: [];
        }

        return new DraftPrompt(
            type: $event->type,
            targetLanguage: $event->targetLanguage,
            languagePair: $context->languagePair,
            leadStatus: $context->status,
            organizationName: $context->organizationName,
            segment: $context->segment,
            contactName: $context->contactName,
            bio: $this->blankToNull($profile ?: [], 'bio'),
            specialties: $this->blankToNull($profile ?: [], 'specialties'),
            signature: $this->blankToNull($profile ?: [], 'signature'),
            templateSubject: $this->blankToNull($template, 'subject'),
            templateBody: $this->blankToNull($template, 'body'),
        );
    }
}
