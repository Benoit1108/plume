<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Prospecting\Application\Command\CancelFollowUp\CancelFollowUp;
use App\Prospecting\Application\Command\ContactLead\ContactLead;
use App\Prospecting\Application\Command\MarkLeadLost\MarkLeadLost;
use App\Prospecting\Application\Command\MarkLeadWon\MarkLeadWon;
use App\Prospecting\Application\Command\MoveToSampleTest\MoveToSampleTest;
use App\Prospecting\Application\Command\PauseLead\PauseLead;
use App\Prospecting\Application\Command\RecordFollowUp\RecordFollowUp;
use App\Prospecting\Application\Command\RecordReply\RecordReply;
use App\Prospecting\Application\Command\ResumeLead\ResumeLead;
use App\Prospecting\Application\Command\ReturnLeadToContact\ReturnLeadToContact;
use App\Prospecting\Application\Query\GetLead\GetLead;
use App\Prospecting\Application\ReadModel\LeadView;
use App\Prospecting\Infrastructure\ApiResource\LeadResource;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Query\QueryBus;

/**
 * Transitions métier (POST /leads/{id}/…) : l'action de l'URI mappe une commande.
 * Transition interdite → IllegalStatusTransition (Conflict) → 409.
 *
 * @implements ProcessorInterface<LeadResource, LeadResource>
 */
final class LeadTransitionProcessor implements ProcessorInterface
{
    private const COMMANDS = [
        'contact' => ContactLead::class,
        'back-to-contact' => ReturnLeadToContact::class,
        'follow-up' => RecordFollowUp::class,
        'reply' => RecordReply::class,
        'sample-test' => MoveToSampleTest::class,
        'win' => MarkLeadWon::class,
        'lose' => MarkLeadLost::class,
        'pause' => PauseLead::class,
        'resume' => ResumeLead::class,
    ];

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LeadResource
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id)) {
            throw new \LogicException('Missing lead id.');
        }

        // DELETE /leads/{id}/follow-up : annulation de la relance planifiée (204).
        if ($operation instanceof HttpOperation && 'DELETE' === $operation->getMethod()) {
            $this->commandBus->dispatch(new CancelFollowUp($id));

            return null;
        }

        $uriTemplate = $operation instanceof HttpOperation ? (string) $operation->getUriTemplate() : '';
        $action = substr($uriTemplate, (int) strrpos($uriTemplate, '/') + 1);
        $commandClass = self::COMMANDS[$action] ?? throw new \LogicException(sprintf('Unknown lead transition "%s".', $action));

        $this->commandBus->dispatch(new $commandClass($id));

        /** @var LeadView $view */
        $view = $this->queryBus->ask(new GetLead($id));

        return LeadProvider::toResource($view);
    }
}
