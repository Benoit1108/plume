<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use App\Account\Infrastructure\Security\TotpService;
use App\Shared\Infrastructure\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * 2FA TOTP — page Compte (V2, slice sécurité). Enrôlement en DEUX temps : `setup` pose un secret
 * candidat (affiché une fois : saisie manuelle dans l'app d'authentification + URI otpauth) ;
 * `confirm` exige un code VALIDE produit avec ce secret avant d'activer (preuve que l'app est
 * bien enrôlée — sinon on enfermerait l'utilisatrice dehors) et retourne les codes de secours EN
 * CLAIR, une seule fois. `disable` exige le mot de passe courant. Statut exposé pour l'UI.
 */
#[AsController]
final class TwoFactorController
{
    public function __construct(
        private readonly Security $security,
        private readonly TotpService $totp,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AuditLogger $audit,
    ) {
    }

    public function status(): Response
    {
        $user = $this->currentUser();

        return new JsonResponse([
            'enabled' => $user->isTotpEnabled(),
            'remainingBackupCodes' => $user->remainingBackupCodes(),
        ]);
    }

    public function setup(): Response
    {
        $user = $this->currentUser();
        if ($user->isTotpEnabled()) {
            return new JsonResponse(['detail' => 'already_enabled'], Response::HTTP_CONFLICT);
        }

        $secret = $this->totp->generateSecret();
        $user->startTotpEnrollment($secret);
        $this->em->flush();

        return new JsonResponse([
            'secret' => $secret,
            'otpauthUri' => $this->totp->provisioningUri($secret, $user->getUserIdentifier()),
        ]);
    }

    public function confirm(Request $request): Response
    {
        $user = $this->currentUser();
        $pending = $user->totpPendingSecret();
        if (null === $pending) {
            return new JsonResponse(['detail' => 'no_pending_enrollment'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        $code = \is_array($payload) && \is_string($payload['code'] ?? null) ? trim($payload['code']) : '';
        if (null === $this->totp->verify($pending, $code)) {
            return new JsonResponse(['detail' => 'invalid_code'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $backupCodes = $this->totp->generateBackupCodes();
        $user->enableTotp($backupCodes['hashed']);
        $this->em->flush();
        $this->audit->record($user->getUserIdentifier(), 'account.2fa_enabled', $user->getTenantId()->toRfc4122());

        // Les codes de secours en CLAIR — affichés UNE seule fois, jamais restockés tels quels.
        return new JsonResponse(['backupCodes' => $backupCodes['plain']]);
    }

    public function disable(Request $request): Response
    {
        $user = $this->currentUser();
        $payload = json_decode($request->getContent(), true);
        $current = \is_array($payload) && \is_string($payload['currentPassword'] ?? null) ? $payload['currentPassword'] : '';
        if (!$this->hasher->isPasswordValid($user, $current)) {
            return new JsonResponse(['detail' => 'invalid_current_password'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->disableTotp();
        $this->em->flush();
        $this->audit->record($user->getUserIdentifier(), 'account.2fa_disabled', $user->getTenantId()->toRfc4122());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('TwoFactorController behind the firewall: a user is always present.');
        }

        return $user;
    }
}
