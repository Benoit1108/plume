<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Identité de la session courante. Avec le token en cookie httpOnly (M2.0),
 * le front ne peut plus lire les claims du JWT : c'est ici qu'il apprend
 * qui est connecté. Derrière le firewall (ROLE_USER).
 */
#[AsController]
final class MeController
{
    public function __construct(private readonly Security $security)
    {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser()
            ?? throw new \LogicException('MeController behind the firewall: a user is always present.');

        return new JsonResponse(['email' => $user->getUserIdentifier()]);
    }
}
