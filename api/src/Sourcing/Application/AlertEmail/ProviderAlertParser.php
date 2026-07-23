<?php

declare(strict_types=1);

namespace App\Sourcing\Application\AlertEmail;

use App\Sourcing\Application\Source\ParsedAlert;

/**
 * Parser fin d'email d'alerte SPÉCIFIQUE à un fournisseur (LinkedIn, ProZ…) : extraction
 * STRUCTURÉE, potentiellement PLUSIEURS offres par email (un digest en contient souvent
 * plusieurs). Sélectionné par l'expéditeur ; s'il ne reconnaît rien, il retourne `[]` et le
 * `AlertEmailParser` retombe sur l'extraction générique (best-effort).
 */
interface ProviderAlertParser
{
    public function supports(string $fromAddress): bool;

    /** @return ParsedAlert[] */
    public function parse(string $subject, string $body, string $externalId): array;
}
