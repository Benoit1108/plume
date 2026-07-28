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

    /**
     * 2FA TOTP (V2, slice sécurité). `totpSecret` non nul ⇒ 2FA ACTIVE (OTP exigé au login).
     * Le secret est stocké tel quel (il DOIT être relu pour vérifier les codes — non hashable) :
     * compromis documenté, la base est protégée par ailleurs (RLS, rôles, sauvegardes chiffrées).
     */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $totpSecret = null;

    /** Secret en cours d'enrôlement : posé au setup, promu par confirm (code valide exigé). */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $totpPendingSecret = null;

    /** Anti-rejeu : dernier pas de temps TOTP accepté (un code ne sert qu'une fois). */
    #[ORM\Column(nullable: true)]
    private ?int $totpLastUsedStep = null;

    /** @var list<string>|null codes de secours HASHÉS (sha256), consommés à l'usage */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $backupCodes = null;

    /**
     * Email vérifié (V2.1b). **Par défaut `true`** : tout compte créé par un opérateur/la CLI
     * (`app:user:create`, seed, tests) est de confiance. Seule l'INSCRIPTION PUBLIQUE en libre-service
     * exige une vérification (`requireEmailVerification()`) → l'auth est refusée tant que non vérifié.
     */
    #[ORM\Column]
    private bool $emailVerified = true;

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

    /** Inscription publique : le compte doit vérifier son email avant de pouvoir se connecter. */
    public function requireEmailVerification(): void
    {
        $this->emailVerified = false;
    }

    /** Vérification réussie (lien email cliqué). Idempotent. */
    public function verifyEmail(): void
    {
        $this->emailVerified = true;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    // --- 2FA TOTP (V2, slice sécurité) ---

    public function isTotpEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function totpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function totpPendingSecret(): ?string
    {
        return $this->totpPendingSecret;
    }

    /** Setup : pose un secret candidat (re-setup = remplace le candidat, jamais l'actif). */
    public function startTotpEnrollment(string $secret): void
    {
        $this->totpPendingSecret = $secret;
    }

    /** Confirm (code valide vérifié PAR L'APPELANT) : promeut le candidat + pose les codes de secours hashés. */
    /** @param list<string> $hashedBackupCodes */
    public function enableTotp(array $hashedBackupCodes): void
    {
        if (null === $this->totpPendingSecret) {
            throw new \LogicException('No pending TOTP enrollment to confirm.');
        }
        $this->totpSecret = $this->totpPendingSecret;
        $this->totpPendingSecret = null;
        $this->totpLastUsedStep = null;
        $this->backupCodes = $hashedBackupCodes;
    }

    public function disableTotp(): void
    {
        $this->totpSecret = null;
        $this->totpPendingSecret = null;
        $this->totpLastUsedStep = null;
        $this->backupCodes = null;
    }

    /** Anti-rejeu : accepte un pas de temps UNE seule fois (strictement croissant). */
    public function acceptTotpStep(int $step): bool
    {
        if (null !== $this->totpLastUsedStep && $step <= $this->totpLastUsedStep) {
            return false;
        }
        $this->totpLastUsedStep = $step;

        return true;
    }

    /** Consomme un code de secours (comparé par hash) : true si valide, alors retiré. */
    public function consumeBackupCode(string $hash): bool
    {
        if (null === $this->backupCodes || !\in_array($hash, $this->backupCodes, true)) {
            return false;
        }
        $this->backupCodes = array_values(array_filter($this->backupCodes, static fn (string $c): bool => $c !== $hash));

        return true;
    }

    public function remainingBackupCodes(): int
    {
        return null === $this->backupCodes ? 0 : \count($this->backupCodes);
    }

    public function deletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }
}
