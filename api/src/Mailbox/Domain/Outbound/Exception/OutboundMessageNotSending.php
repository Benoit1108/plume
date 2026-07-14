<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Exception;

use App\Mailbox\Domain\Outbound\OutboundStatus;
use App\Shared\Domain\Exception\Conflict;

/**
 * Résultat d'envoi reçu pour un message qui n'en attend pas (redélivrance
 * Messenger) : jamais de double envoi comptabilisé, jamais de SENT rétrogradé.
 */
final class OutboundMessageNotSending extends Conflict
{
    public static function inStatus(OutboundStatus $status): self
    {
        return new self(sprintf('Outbound message is not awaiting send (status "%s").', $status->value));
    }
}
