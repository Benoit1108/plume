<?php

declare(strict_types=1);

namespace App\Notification\Application\ReadModel;

/** Une notification telle qu'affichée (vue immuable). */
final class NotificationView
{
    /**
     * @param array<string, mixed> $payload données propres au type (leadId, preview, orgName…)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $payload,
        public readonly ?string $readAt,
        public readonly string $occurredOn,
    ) {
    }
}
