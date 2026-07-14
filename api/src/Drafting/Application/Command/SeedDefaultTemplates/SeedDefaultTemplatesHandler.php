<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\SeedDefaultTemplates;

use App\Drafting\Domain\Draft\DraftType;
use App\Drafting\Domain\Template\Template;
use App\Drafting\Domain\Template\TemplateId;
use App\Drafting\Domain\Template\TemplateRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Seed des 3 gabarits de départ (décision M1.4 n°6) — idempotent :
 * ne fait rien si des gabarits existent déjà.
 */
final class SeedDefaultTemplatesHandler implements CommandHandler
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly EventBus $eventBus,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SeedDefaultTemplates $command): void
    {
        if ($this->templates->count() > 0) {
            return;
        }

        $tenantId = TenantId::fromString($command->tenantId);
        $now = $this->clock->now();

        $seeds = [
            [
                'name' => 'Candidature édition (FR)',
                'type' => DraftType::APPLICATION_EMAIL,
                'segment' => Segment::PUBLISHING,
                'language' => 'fr',
                'subject' => 'Candidature — traduction {{langues}}',
                'body' => "Bonjour {{contact}},\n\nTraductrice indépendante ({{langues}}), je me permets de contacter {{organisation}} pour proposer mes services de traduction littéraire.\n\n{{bio}}\n\nJe serais ravie d'échanger sur vos besoins et de réaliser un essai sur un extrait de votre choix.\n\n{{signature}}",
            ],
            [
                'name' => 'Candidature audiovisuel (EN)',
                'type' => DraftType::APPLICATION_EMAIL,
                'segment' => Segment::AUDIOVISUAL,
                'language' => 'en',
                'subject' => 'Freelance subtitling — {{langues}}',
                'body' => "Hello {{contact}},\n\nI am a freelance audiovisual translator ({{langues}}) reaching out to {{organisation}} to offer my subtitling and dubbing adaptation services.\n\n{{bio}}\n\nI would be happy to take a short test to show you my work.\n\n{{signature}}",
            ],
            [
                'name' => 'Relance (FR)',
                'type' => DraftType::FOLLOW_UP_EMAIL,
                'segment' => Segment::PUBLISHING,
                'language' => 'fr',
                'subject' => 'Re : candidature — traduction {{langues}}',
                'body' => "Bonjour {{contact}},\n\nJe me permets de revenir vers vous au sujet de ma candidature adressée à {{organisation}}.\n\nJe reste bien sûr disponible pour un essai ou un échange.\n\n{{signature}}",
            ],
        ];

        foreach ($seeds as $seed) {
            $template = Template::create(
                TemplateId::fromString($this->ids->generate()),
                $tenantId,
                $seed['name'],
                $seed['type'],
                $seed['segment'],
                LanguageCode::fromString($seed['language']),
                $seed['subject'],
                $seed['body'],
                $now,
            );
            $this->templates->save($template);
            $this->eventBus->publish(...$template->pullDomainEvents());
        }
    }
}
