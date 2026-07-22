<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

/**
 * Marqueur optionnel : un message/event qui porte un tenant et dont le traitement worker doit
 * être scopé. Par défaut le middleware lit la convention (propriété publique `tenantId`) ;
 * implémenter cette interface permet de l'exposer explicitement quand la convention ne suffit pas.
 */
interface TenantAware
{
    public function messageTenantId(): string;
}
