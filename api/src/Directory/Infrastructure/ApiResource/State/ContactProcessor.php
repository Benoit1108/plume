<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Directory\Application\Command\AddContact\AddContact;
use App\Directory\Application\Command\RemoveContact\RemoveContact;
use App\Directory\Application\Command\UpdateContact\UpdateContact;
use App\Directory\Infrastructure\ApiResource\ContactResource;
use App\Shared\Application\Command\CommandBus;
use Symfony\Component\Uid\Uuid;

/**
 * Écriture des contacts (POST/PATCH/DELETE sous une organisation) -> CommandBus.
 *
 * @implements ProcessorInterface<ContactResource, ContactResource|null>
 */
final class ContactProcessor implements ProcessorInterface
{
    public function __construct(private readonly CommandBus $commandBus)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ContactResource
    {
        $organizationId = self::stringVar($uriVariables, 'organizationId');

        if ($operation instanceof Delete) {
            $this->commandBus->dispatch(new RemoveContact($organizationId, self::stringVar($uriVariables, 'id')));

            return null;
        }

        if ($operation instanceof Post) {
            $contactId = Uuid::v7()->toRfc4122();
            $this->commandBus->dispatch(new AddContact(
                $organizationId,
                $contactId,
                $data->fullName,
                $data->role,
                $data->email,
                $data->phone,
                $data->linkedinUrl,
                $data->preferredLanguage,
            ));
            $data->id = $contactId;

            return $data;
        }

        $contactId = self::stringVar($uriVariables, 'id');
        $this->commandBus->dispatch(new UpdateContact(
            $organizationId,
            $contactId,
            $data->fullName,
            $data->role,
            $data->email,
            $data->phone,
            $data->linkedinUrl,
            $data->preferredLanguage,
        ));
        $data->id = $contactId;

        return $data;
    }

    /** @param array<string, mixed> $uriVariables */
    private static function stringVar(array $uriVariables, string $key): string
    {
        $value = $uriVariables[$key] ?? null;

        return \is_string($value) ? $value : '';
    }
}
