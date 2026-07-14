<?php

declare(strict_types=1);

namespace App\Drafting\Application\ReadModel;

/** Port de lecture des gabarits (fail-closed tenant). */
interface TemplateSearch
{
    /** @return TemplateView[] triés par nom */
    public function all(): array;

    /** @throws \App\Drafting\Domain\Template\Exception\TemplateNotFound */
    public function get(string $id): TemplateView;
}
