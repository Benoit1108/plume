<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Jeton de réinitialisation de mot de passe (V2.1a — mot de passe oublié). Ni le jeton en clair ni
 * un lien vers `app_user` ne sont stockés : seulement le **hash** du jeton (le clair n'existe que
 * dans l'email envoyé), l'email cible et une expiration courte. Hors RLS (utilisé AVANT le tenant,
 * comme `app_user`/`refresh_tokens`). À usage unique : consommé (supprimé) à la réinitialisation.
 */
#[ORM\Entity]
#[ORM\Table(name: 'password_reset_token')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /** @var non-empty-string */
    #[ORM\Column(length: 180)]
    private string $email;

    /** SHA-256 hex (64 car.) du jeton — jamais le jeton en clair. */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param non-empty-string $email
     */
    public function __construct(Uuid $id, string $email, string $tokenHash, \DateTimeImmutable $expiresAt, \DateTimeImmutable $createdAt)
    {
        $this->id = $id;
        $this->email = $email;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }
}
