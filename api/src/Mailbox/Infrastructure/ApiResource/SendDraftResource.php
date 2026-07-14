<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Drafting\Infrastructure\ApiResource\DraftResource;
use App\Mailbox\Infrastructure\ApiResource\State\SendDraftProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Envoi d'un brouillon relu (draft-first : READY uniquement). Asynchrone :
 * la réponse est l'ACCUSÉ (SENDING) ; le journal de la piste raconte la suite
 * (email_sent / email_send_failed).
 */
#[ApiResource(
    shortName: 'SendDraft',
    normalizationContext: ['groups' => ['send:read']],
    operations: [
        new Post(
            uriTemplate: '/drafts/{id}/send',
            uriVariables: ['id' => new Link(fromClass: DraftResource::class)],
            input: false,
            status: 202,
            processor: SendDraftProcessor::class,
            openapi: new \ApiPlatform\OpenApi\Model\Operation(summary: 'Envoyer le brouillon (asynchrone, boîte connectée requise)'),
        ),
    ],
)]
final class SendDraftResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['send:read'])]
    public ?string $id = null;

    #[Groups(['send:read'])]
    public string $status = 'SENDING';
}
