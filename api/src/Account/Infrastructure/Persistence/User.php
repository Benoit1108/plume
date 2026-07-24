<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Utilisateur de sécurité (Infrastructure) — porteur du tenant.
 * Le profil métier (Translator/Profile) viendra en M1 dans Account\Domain.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'uuid')]
    private Uuid $tenantId;

    /** @var non-empty-string */
    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private string $password = '';

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /**
     * RGPD — demande de suppression de compte (soft-delete). Non nul ⇒ compte désactivé
     * immédiatement (l'auth est refusée, la relève de fond s'arrête) ; purge physique après
     * le délai de grâce (V2.0-a2).
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletionRequestedAt = null;

    public function __construct(Uuid $id, Uuid $tenantId, string $email)
    {
        if ('' === $email) {
            throw new \InvalidArgumentException('User email cannot be empty.');
        }

        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->email = $email;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function eraseCredentials(): void
    {
    }

    /** RGPD — marque le compte pour suppression (soft-delete). Idempotent : conserve la 1re demande. */
    public function requestDeletion(\DateTimeImmutable $at): void
    {
        $this->deletionRequestedAt ??= $at;
    }

    public function isDeletionRequested(): bool
    {
        return null !== $this->deletionRequestedAt;
    }

    public function deletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }
}
