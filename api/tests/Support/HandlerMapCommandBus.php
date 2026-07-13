<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Application\Command\Command;
use App\Shared\Application\Command\CommandBus;

/** Bus synchrone minimal : classe de commande -> handler (tests d'application). */
final class HandlerMapCommandBus implements CommandBus
{
    /** @param array<class-string, callable(Command): void> $handlers */
    public function __construct(private readonly array $handlers)
    {
    }

    public function dispatch(Command $command): void
    {
        $handler = $this->handlers[$command::class] ?? throw new \LogicException(sprintf('No handler mapped for %s.', $command::class));
        $handler($command);
    }
}
