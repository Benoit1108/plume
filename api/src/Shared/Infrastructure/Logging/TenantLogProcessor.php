<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Ajoute le tenant courant à chaque log (débuggabilité multi-tenant).
 * Autoconfiguré comme processor Monolog (implémente ProcessorInterface).
 */
final class TenantLogProcessor implements ProcessorInterface
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $tenant = $this->tenantContext->get();
        if (null !== $tenant) {
            $record->extra['tenant_id'] = $tenant->toString();
        }

        return $record;
    }
}
