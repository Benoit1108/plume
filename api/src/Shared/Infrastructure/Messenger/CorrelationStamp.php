<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Transporte l'identifiant de corrélation de la requête HTTP jusqu'au message async, pour que les
 * logs du worker déclenché par cette requête partagent le même `request_id`. Sérialisable (simple
 * chaîne) → survit au transport (table messenger_messages).
 */
final class CorrelationStamp implements StampInterface
{
    public function __construct(public readonly string $requestId)
    {
    }
}
