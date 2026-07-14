<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\ApiResource\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Mailbox\Application\MailboxConnectorRegistry;
use App\Mailbox\Domain\Mailbox\MailProviderName;
use App\Mailbox\Infrastructure\ApiResource\MailboxResource;
use App\Mailbox\Infrastructure\OAuth\OAuthStateCodec;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;

/**
 * POST /mailbox/oauth/start {provider} : émet le state anti-CSRF (lié au tenant
 * ET au fournisseur choisi) et renvoie l'URL de consentement du bon fournisseur.
 *
 * @implements ProcessorInterface<MailboxResource, MailboxResource>
 */
final class OAuthStartProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MailboxConnectorRegistry $connectors,
        private readonly OAuthStateCodec $stateCodec,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MailboxResource
    {
        $tenant = $this->tenantContext->require();
        $provider = MailProviderName::tryFrom($data->provider)
            ?? throw InvalidValue::because(sprintf('Unknown mail provider "%s".', $data->provider));

        $state = $this->stateCodec->issue($tenant->toString(), $provider->value);

        $resource = new MailboxResource();
        $resource->authorizationUrl = $this->connectors->connectorFor($provider->value)->authorizationUrl($state);

        return $resource;
    }
}
