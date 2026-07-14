<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Infrastructure\ApiResource\MailboxResource;
use App\Mailbox\Infrastructure\OAuth\OAuthStateCodec;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * POST /mailbox/oauth/start : émet le state anti-CSRF (lié au tenant) et
 * renvoie l'URL de consentement. Le navigateur y est envoyé par le front.
 *
 * @implements ProcessorInterface<MailboxResource, MailboxResource>
 */
final class OAuthStartProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MailboxConnector $connector,
        private readonly OAuthStateCodec $stateCodec,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MailboxResource
    {
        $tenant = $this->tenantContext->require();

        $resource = new MailboxResource();
        $resource->authorizationUrl = $this->connector->authorizationUrl($this->stateCodec->issue($tenant->toString()));

        return $resource;
    }
}
