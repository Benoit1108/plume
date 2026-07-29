<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Account\Infrastructure\Persistence\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Provider d'utilisateurs INSENSIBLE À LA CASSE de l'email (revue globale P0-2). Remplace le provider
 * `entity` par défaut (comparaison exacte) : l'inscription normalise l'email en minuscules, mais le
 * login et le rechargement stateless doivent matcher quelle que soit la casse saisie — sinon
 * `Jane@Example.com` enregistrée `jane@example.com` ne peut plus se reconnecter (lockout silencieux).
 * Point UNIQUE : couvre les firewalls login / refresh / api.
 *
 * @implements UserProviderInterface<User>
 */
final class AppUserProvider implements UserProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($identifier))]);
        if (null === $user) {
            throw new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
