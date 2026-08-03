<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Uid;

use App\Prospecting\Domain\Lead\FollowUpId;
use App\Prospecting\Domain\Lead\FollowUpIds;
use App\Shared\Application\IdGenerator;

/** Fournit des identifiants de relance en UUID v7 (via le port partagé `IdGenerator`). */
final class Uuid7FollowUpIds implements FollowUpIds
{
    public function __construct(private readonly IdGenerator $ids)
    {
    }

    public function next(): FollowUpId
    {
        return FollowUpId::fromString($this->ids->generate());
    }
}
