<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use App\Shared\Application\Query\Query;
use App\Shared\Application\Query\QueryBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerQueryBus implements QueryBus
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    public function ask(Query $query): mixed
    {
        try {
            return $this->handle($query);
        } catch (HandlerFailedException $e) {
            // Symétrique du CommandBus : l'exception métier d'origine remonte nue
            // (mappée vers HTTP par api_platform.exception_to_status).
            throw $e->getPrevious() ?? $e;
        }
    }
}
