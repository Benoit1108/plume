<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Account\Infrastructure\Persistence\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Ajoute le claim `tenant_id` au JWT à sa création. */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
final class JwtTenantClaimListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $data = $event->getData();
        $data['tenant_id'] = $user->getTenantId()->toRfc4122();
        $event->setData($data);
    }
}
