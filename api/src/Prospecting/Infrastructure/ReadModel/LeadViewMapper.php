<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ReadModel;

use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Domain\Lead\PipelineStatus;
use App\Shared\Infrastructure\Persistence\Doctrine\HydratesRows;

/** Hydratation ligne SQL → LeadView, partagée par les read models du pipeline. */
final class LeadViewMapper
{
    use HydratesRows;

    public const COLUMNS = "l.id, l.organization_id, l.contact_id, l.language_pair, l.source, l.priority, l.segment, l.status, l.created_at, l.last_contacted_at, l.last_reply_at, l.next_follow_up_at, l.next_follow_up_label, o.name AS organization_name, EXISTS (SELECT 1 FROM jsonb_array_elements(COALESCE(o.contacts, '[]'::jsonb)) e WHERE COALESCE(e->>'email', '') <> '') AS has_reachable_contact";
    public const FROM = 'FROM lead l LEFT JOIN organization o ON o.id = l.organization_id AND o.tenant_id = l.tenant_id';

    /** @param array<string, mixed> $row */
    public function map(array $row): LeadView
    {
        $status = PipelineStatus::from($this->str($row, 'status'));

        return new LeadView(
            $this->str($row, 'id'),
            $this->str($row, 'organization_id'),
            $this->str($row, 'organization_name'),
            $this->strOrNull($row, 'contact_id'),
            $this->str($row, 'language_pair'),
            $this->str($row, 'source'),
            $this->str($row, 'priority'),
            $this->str($row, 'segment'),
            $status->value,
            $this->allowedActions($status),
            $this->date($row, 'created_at') ?? new \DateTimeImmutable('@0'),
            $this->date($row, 'last_contacted_at'),
            $this->date($row, 'last_reply_at'),
            $this->date($row, 'next_follow_up_at'),
            $this->strOrNull($row, 'next_follow_up_label'),
            $this->bool($row['has_reachable_contact'] ?? false),
        );
    }

    /**
     * Statut → actions proposables à l'UI. Depuis PAUSED, seule la reprise a du
     * sens (retour au statut mémorisé) ; TO_CONTACT n'est pas une action directe.
     *
     * @return string[]
     */
    private function allowedActions(PipelineStatus $status): array
    {
        if (PipelineStatus::PAUSED === $status) {
            return ['resume'];
        }

        $actionByTarget = [
            PipelineStatus::TO_CONTACT->value => 'back-to-contact',
            PipelineStatus::CONTACTED->value => 'contact',
            PipelineStatus::FOLLOWED_UP->value => 'follow-up',
            PipelineStatus::IN_DISCUSSION->value => 'reply',
            PipelineStatus::SAMPLE_TEST->value => 'sample-test',
            PipelineStatus::WON->value => 'win',
            PipelineStatus::LOST->value => 'lose',
            PipelineStatus::PAUSED->value => 'pause',
        ];

        $actions = [];
        foreach ($status->allowedTransitions() as $target) {
            $actions[] = $actionByTarget[$target->value]; // map exhaustive sur les statuts
        }

        return array_values(array_unique($actions));
    }
}
