<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Notification\Infrastructure\ApiResource\State\MarkAllNotificationsReadProcessor;
use App\Notification\Infrastructure\ApiResource\State\MarkNotificationReadProcessor;
use App\Notification\Infrastructure\ApiResource\State\NotificationsProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Centre de notifications (V2) : les 50 dernières du tenant courant + marquage lu.
 * Projection pure (aucune écriture métier) — le front dérive le compteur non-lu de la liste.
 */
#[ApiResource(
    shortName: 'Notification',
    normalizationContext: ['groups' => ['notification:read']],
    operations: [
        new GetCollection(uriTemplate: '/notifications', provider: NotificationsProvider::class),
        new Post(
            uriTemplate: '/notifications/{id}/read',
            processor: MarkNotificationReadProcessor::class,
            input: false,
            output: false,
            status: 204,
        ),
        new Post(
            uriTemplate: '/notifications/read-all',
            processor: MarkAllNotificationsReadProcessor::class,
            input: false,
            output: false,
            status: 204,
        ),
    ],
)]
final class NotificationResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['notification:read'])]
    public string $id = '';

    /** Type stable (i18n front : notifications.types.*). */
    #[ApiProperty(openapiContext: ['enum' => ['reply_received', 'email_send_failed', 'followup_due']])]
    #[Groups(['notification:read'])]
    public string $type = '';

    /** @var array<string, mixed> données propres au type (leadId, preview, orgName, label…) */
    #[Groups(['notification:read'])]
    public array $payload = [];

    #[Groups(['notification:read'])]
    public ?string $readAt = null;

    #[Groups(['notification:read'])]
    public string $occurredOn = '';
}
