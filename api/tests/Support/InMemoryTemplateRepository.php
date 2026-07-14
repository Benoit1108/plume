<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Drafting\Domain\Template\Exception\TemplateNotFound;
use App\Drafting\Domain\Template\Template;
use App\Drafting\Domain\Template\TemplateId;
use App\Drafting\Domain\Template\TemplateRepository;

final class InMemoryTemplateRepository implements TemplateRepository
{
    /** @var array<string, Template> */
    private array $templates = [];

    public function save(Template $template): void
    {
        $this->templates[$template->id()->toString()] = $template;
    }

    public function get(TemplateId $id): Template
    {
        return $this->templates[$id->toString()] ?? throw TemplateNotFound::withId($id);
    }

    public function remove(Template $template): void
    {
        unset($this->templates[$template->id()->toString()]);
    }

    public function count(): int
    {
        return \count($this->templates);
    }
}
