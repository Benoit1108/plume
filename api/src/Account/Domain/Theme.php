<?php

declare(strict_types=1);

namespace App\Account\Domain;

enum Theme: string
{
    case LIGHT = 'light';
    case DARK = 'dark';
    case SYSTEM = 'system';
}
