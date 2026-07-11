<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * À l'authentification JWT : alimente le TenantContext et active le filtre Doctrine
 * d'isolation multi-tenant avec le tenant du token.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
final class TenantContextListener
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(JWTAuthenticatedEvent $event): void
    {
        $payload = $event->getPayload();
        $tenantId = $payload['tenant_id'] ?? null;
        if (!\is_string($tenantId) || '' === $tenantId) {
            return;
        }

        $this->tenantContext->set(TenantId::fromString($tenantId));
        $this->em->getFilters()->enable('tenant')->setParameter('tenant_id', $tenantId);
    }
}
