<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Template;

interface TemplateRepository
{
    public function save(Template $template): void;

    /** @throws Exception\TemplateNotFound si introuvable (dans le périmètre du tenant) */
    public function get(TemplateId $id): Template;

    public function remove(Template $template): void;

    public function count(): int;
}
