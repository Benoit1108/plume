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
 *
 * Les contrôles sont en **post**-authentification (revue SEC-P2a) : en pré-auth, Symfony les évalue
 * AVANT la vérification du mot de passe, si bien qu'un mot de passe quelconque suffisait à
 * distinguer un email inscrit (`email_not_verified`) d'un email libre (« Identifiants invalides »)
 * — une énumération de comptes, alors que le mot de passe oublié est délibérément anti-énumération.
 * En post-auth, les codes stables ne sortent qu'à quelqu'un qui a DÉJÀ prouvé connaître le mot de
 * passe ; les requêtes JWT/refresh restent contrôlées (le succès d'authentification les déclenche).
 */
final class AccountStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        // Messages = codes STABLES : le front les distingue (email non vérifié → propose le renvoi).
        if ($user->isDeletionRequested()) {
            throw new CustomUserMessageAccountStatusException('account_deleted');
        }
        // Inscription publique : pas d'accès tant que l'email n'est pas vérifié (V2.1b).
        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException('email_not_verified');
        }
    }
}
