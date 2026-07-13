<?php

declare(strict_types=1);

namespace App\Account\Application\ReadModel;

/** Port de lecture du profil courant (fail-closed tenant en Infrastructure). */
interface ProfileSettings
{
    public function current(): ProfileView;
}
