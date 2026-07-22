<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * À l'authentification JWT : active le tenant du token (contexte + filtre Doctrine) via le
 * point unique TenantScope.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
final class TenantContextListener
{
    public function __construct(private readonly TenantScope $tenantScope)
    {
    }

    public function __invoke(JWTAuthenticatedEvent $event): void
    {
        $payload = $event->getPayload();
        $tenantId = $payload['tenant_id'] ?? null;
        if (!\is_string($tenantId) || '' === $tenantId) {
            return;
        }

        $this->tenantScope->activate(TenantId::fromString($tenantId));
    }
}
