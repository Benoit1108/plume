<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Drafting\Domain\Draft\Draft;
use App\Drafting\Domain\Draft\DraftId;
use App\Drafting\Domain\Draft\DraftRepository;
use App\Drafting\Domain\Draft\Exception\DraftNotFound;

final class InMemoryDraftRepository implements DraftRepository
{
    /** @var array<string, Draft> */
    private array $drafts = [];

    public function save(Draft $draft): void
    {
        $this->drafts[$draft->id()->toString()] = $draft;
    }

    public function get(DraftId $id): Draft
    {
        return $this->drafts[$id->toString()] ?? throw DraftNotFound::withId($id);
    }

    public function remove(Draft $draft): void
    {
        unset($this->drafts[$draft->id()->toString()]);
    }
}
