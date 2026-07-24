<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Account\Infrastructure\Persistence\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * RGPD — un compte dont la suppression a été demandée (soft-delete) est désactivé IMMÉDIATEMENT :
 * ce vérificateur refuse toute authentification (login ET rechargement stateless de chaque requête
 * JWT), avant que le tenant ne soit activé. C'est le pendant « accès » de la purge différée.
 */
final class AccountStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && $user->isDeletionRequested()) {
            throw new CustomUserMessageAccountStatusException('Account scheduled for deletion.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
