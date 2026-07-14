<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Template;

use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Template\Event\TemplateCreated;
use App\Drafting\Domain\Template\Event\TemplateDeleted;
use App\Drafting\Domain\Template\Event\TemplateUpdated;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Modèle (gabarit) de message — agrégat du contexte Drafting.
 * Squelette réutilisable par type/segment/langue, avec variables
 * ({{organisation}}, {{contact}}, {{signature}}…) ; sert de canevas au générateur.
 */
final class Template extends AggregateRoot
{
    private function __construct(
        private readonly TemplateId $id,
        private readonly TenantId $tenantId,
        private string $name,
        private DraftType $type,
        private Segment $segment,
        private LanguageCode $language,
        private ?string $subject,
        private string $body,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        TemplateId $id,
        TenantId $tenantId,
        string $name,
        DraftType $type,
        Segment $segment,
        LanguageCode $language,
        ?string $subject,
        string $body,
        \DateTimeImmutable $now,
    ): self {
        $template = new self(
            $id,
            $tenantId,
            self::guardName($name),
            $type,
            $segment,
            $language,
            $subject,
            self::guardBody($body),
            $now,
        );
        $template->recordEvent(new TemplateCreated($tenantId->toString(), $id->toString(), $now));

        return $template;
    }

    public function update(
        string $name,
        DraftType $type,
        Segment $segment,
        LanguageCode $language,
        ?string $subject,
        string $body,
        \DateTimeImmutable $now,
    ): void {
        $this->name = self::guardName($name);
        $this->type = $type;
        $this->segment = $segment;
        $this->language = $language;
        $this->subject = $subject;
        $this->body = self::guardBody($body);
        $this->recordEvent(new TemplateUpdated($this->tenantId->toString(), $this->id->toString(), $now));
    }

    /** Trace la suppression (le retrait effectif est fait par le repository). */
    public function delete(\DateTimeImmutable $now): void
    {
        $this->recordEvent(new TemplateDeleted($this->tenantId->toString(), $this->id->toString(), $now));
    }

    private static function guardName(string $name): string
    {
        $trimmed = trim($name);
        if ('' === $trimmed) {
            throw InvalidValue::because('Template name cannot be empty.');
        }

        return $trimmed;
    }

    private static function guardBody(string $body): string
    {
        if ('' === trim($body)) {
            throw InvalidValue::because('Template body cannot be empty.');
        }

        return $body;
    }

    public function id(): TemplateId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): DraftType
    {
        return $this->type;
    }

    public function segment(): Segment
    {
        return $this->segment;
    }

    public function language(): LanguageCode
    {
        return $this->language;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
