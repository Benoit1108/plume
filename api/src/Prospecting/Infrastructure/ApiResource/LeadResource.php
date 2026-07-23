<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Prospecting\Infrastructure\ApiResource\State\LeadProvider;
use App\Prospecting\Infrastructure\ApiResource\State\LeadTransitionProcessor;
use App\Prospecting\Infrastructure\ApiResource\State\LeadWriteProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Piste (DTO) — API orientée cas d'usage : les transitions sont des POST dédiés
 * (pas de PATCH de statut), chacune mappe une commande métier. Transition
 * interdite → 409 (mapping Conflict).
 */
#[ApiResource(
    shortName: 'Lead',
    normalizationContext: ['groups' => ['lead:read']],
    denormalizationContext: ['groups' => ['lead:write']],
    operations: [
        new GetCollection(
            provider: LeadProvider::class,
            parameters: [
                'status' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['TO_CONTACT', 'CONTACTED', 'FOLLOWED_UP', 'IN_DISCUSSION', 'SAMPLE_TEST', 'WON', 'LOST', 'PAUSED']], description: 'Filtre par statut du pipeline'),
                'priority' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['LOW', 'MEDIUM', 'HIGH']], description: 'Filtre par priorité'),
                'segment' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['PUBLISHING', 'AUDIOVISUAL', 'TECHNICAL', 'OTHER']], description: 'Filtre par segment'),
                'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1]),
                'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100]),
            ],
        ),
        new Get(provider: LeadProvider::class),
        new Post(processor: LeadWriteProcessor::class),
        // Transitions métier — POST sans corps, retournent la piste mise à jour.
        new Post(uriTemplate: '/leads/{id}/contact', name: 'lead_contact', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Marquer la piste contactée')),
        new Post(uriTemplate: '/leads/{id}/back-to-contact', name: 'lead_back_to_contact', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Repasser à « À contacter » (corrige un contact par erreur)')),
        new Post(uriTemplate: '/leads/{id}/reply', name: 'lead_reply', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Enregistrer une réponse (ouvre la discussion)')),
        new Post(uriTemplate: '/leads/{id}/sample-test', name: 'lead_sample_test', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Passer au test/échantillon')),
        new Post(uriTemplate: '/leads/{id}/win', name: 'lead_win', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Marquer gagnée')),
        new Post(uriTemplate: '/leads/{id}/lose', name: 'lead_lose', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Marquer perdue')),
        new Post(uriTemplate: '/leads/{id}/pause', name: 'lead_pause', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Mettre en pause')),
        new Post(uriTemplate: '/leads/{id}/resume', name: 'lead_resume', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Reprendre (retour au statut mémorisé)')),
        new Post(uriTemplate: '/leads/{id}/follow-up', name: 'lead_follow_up', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, input: false, status: 200, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Relance faite (la cadence planifie la suivante)')),
        new Delete(uriTemplate: '/leads/{id}/follow-up', name: 'lead_cancel_follow_up', provider: LeadProvider::class, processor: LeadTransitionProcessor::class, openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Annuler la relance planifiée')),
    ],
)]
final class LeadResource
{
    #[Groups(['lead:read'])]
    public ?string $id = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[Groups(['lead:read', 'lead:write'])]
    public string $organizationId = '';

    #[Groups(['lead:read'])]
    public ?string $organizationName = null;

    #[Assert\Uuid]
    #[Groups(['lead:read', 'lead:write'])]
    public ?string $contactId = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}>[a-zA-Z]{2}$/', message: 'Paire de langues attendue au format « en>fr ».')]
    #[Groups(['lead:read', 'lead:write'])]
    public string $languagePair = '';

    #[Assert\Choice(['DIRECT', 'REFERRAL', 'JOB_BOARD', 'PROZ', 'LINKEDIN', 'TRANSLATORSCAFE', 'RSS', 'OTHER'])]
    #[Groups(['lead:read', 'lead:write'])]
    public string $source = 'DIRECT';

    #[Assert\Choice(['LOW', 'MEDIUM', 'HIGH'])]
    #[Groups(['lead:read', 'lead:write'])]
    public string $priority = 'MEDIUM';

    #[Assert\Choice(['PUBLISHING', 'AUDIOVISUAL', 'TECHNICAL', 'OTHER'])]
    #[Groups(['lead:read', 'lead:write'])]
    public string $segment = 'OTHER';

    #[Groups(['lead:read'])]
    public string $status = 'TO_CONTACT';

    /** @var string[] actions de transition proposables (contact, reply, win…) */
    #[Groups(['lead:read'])]
    public array $allowedActions = [];

    /** L'organisation a-t-elle un contact avec email (sinon « Contacter » demande confirmation). */
    #[Groups(['lead:read'])]
    public bool $hasReachableContact = false;

    #[Groups(['lead:read'])]
    public ?string $createdAt = null;

    #[Groups(['lead:read'])]
    public ?string $lastContactedAt = null;

    #[Groups(['lead:read'])]
    public ?string $lastReplyAt = null;

    #[Groups(['lead:read'])]
    public ?string $nextFollowUpAt = null;

    #[Groups(['lead:read'])]
    public ?string $nextFollowUpLabel = null;
}
