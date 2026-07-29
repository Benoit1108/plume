<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Ajoute l'identifiant de corrélation (`request_id`) à chaque log, quand il y en a un.
 * Autoconfiguré comme processor Monolog (implémente ProcessorInterface) — cf. TenantLogProcessor.
 */
final class CorrelationIdProcessor implements ProcessorInterface
{
    public function __construct(private readonly CorrelationContext $correlation)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = $this->correlation->get();
        if (null !== $requestId) {
            $record->extra['request_id'] = $requestId;
        }

        return $record;
    }
}
