<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/**
 * Source d'identifiants de relance, injectée dans les méthodes d'agrégat qui auto-planifient
 * (`contact`, `recordFollowUp`, `scheduleFollowUp`). Le domaine ne génère plus d'identifiant
 * lui-même (pureté / déterminisme des tests) : l'implémentation Infrastructure fournit des UUID v7.
 */
interface FollowUpIds
{
    public function next(): FollowUpId;
}
