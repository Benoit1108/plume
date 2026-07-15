<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Mailbox\Domain\Outbound\Exception\OutboundMessageNotFound;
use App\Mailbox\Domain\Outbound\OutboundMessage;
use App\Mailbox\Domain\Outbound\OutboundMessageId;
use App\Mailbox\Domain\Outbound\OutboundMessageRepository;
use App\Mailbox\Domain\Outbound\OutboundStatus;

final class InMemoryOutboundMessageRepository implements OutboundMessageRepository
{
    /** @var array<string, OutboundMessage> */
    private array $messages = [];

    public function save(OutboundMessage $message): void
    {
        $this->messages[$message->id()->toString()] = $message;
    }

    public function get(OutboundMessageId $id): OutboundMessage
    {
        return $this->messages[$id->toString()] ?? throw OutboundMessageNotFound::withId($id->toString());
    }

    public function existsActiveForDraft(string $tenantId, string $draftId): bool
    {
        foreach ($this->messages as $message) {
            if ($message->tenantId()->toString() === $tenantId
                && $message->draftId() === $draftId
                && OutboundStatus::FAILED !== $message->status()) {
                return true;
            }
        }

        return false;
    }
}
