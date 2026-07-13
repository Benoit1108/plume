<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/** Priorité de la piste (tri du kanban et de « à faire aujourd'hui »). */
enum Priority: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
}
