<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound;

interface OutboundMessageRepository
{
    public function save(OutboundMessage $message): void;

    /** @throws Exception\OutboundMessageNotFound */
    public function get(OutboundMessageId $id): OutboundMessage;

    /** Un envoi non-FAILED existe-t-il déjà pour ce brouillon (anti double envoi) ? */
    public function existsActiveForDraft(string $tenantId, string $draftId): bool;
}
