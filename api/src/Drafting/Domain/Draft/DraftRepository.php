<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft;

interface DraftRepository
{
    public function save(Draft $draft): void;

    /** @throws Exception\DraftNotFound si introuvable (dans le périmètre du tenant) */
    public function get(DraftId $id): Draft;

    public function remove(Draft $draft): void;
}
