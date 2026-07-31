<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Account\Application\Crypto\SecretCipher;
use App\Account\Application\Crypto\SecretCipherFailure;
use App\Account\Infrastructure\Persistence\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * 2FA au login (V2, slice sécurité). S'exécute APRÈS la vérification du mot de passe (priorité
 * négative sur CheckPassportEvent) et UNIQUEMENT sur /api/v1/login_check : si la 2FA du compte est
 * active, exige un champ `otp` (code TOTP à 6 chiffres, ou code de secours à usage unique).
 *
 * Messages STABLES pour le front : `2fa_required` (mot de passe OK, OTP manquant → afficher le
 * champ) / `2fa_invalid` (OTP faux). ANTI-REJEU : le pas de temps accepté est mémorisé — un code
 * déjà utilisé est refusé même dans sa fenêtre. Les échecs comptent dans le login_throttling.
 */
final class TwoFactorLoginListener
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly TotpService $totp,
        private readonly SecretCipher $cipher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[AsEventListener(event: CheckPassportEvent::class, priority: -10)]
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request || '/api/v1/login_check' !== $request->getPathInfo()) {
            return;
        }

        $user = $event->getPassport()->getUser();
        if (!$user instanceof User || !$user->isTotpEnabled()) {
            return;
        }

        $payload = json_decode((string) $request->getContent(), true);
        $otp = \is_array($payload) && \is_string($payload['otp'] ?? null) ? trim($payload['otp']) : '';
        if ('' === $otp) {
            throw new CustomUserMessageAuthenticationException('2fa_required');
        }

        $secret = $user->totpSecret();
        \assert(null !== $secret);
        try {
            $secret = $this->cipher->decrypt($secret); // chiffré au repos (ADR-0027)
        } catch (SecretCipherFailure) {
            // Secret illisible (clé changée) : le TOTP ne peut pas matcher — reste le code de secours.
            $secret = null;
        }

        // 1) Code TOTP — avec anti-rejeu (le pas de temps ne sert qu'une fois).
        $step = null === $secret ? null : $this->totp->verify($secret, $otp);
        if (null !== $step) {
            if (!$user->acceptTotpStep($step)) {
                throw new CustomUserMessageAuthenticationException('2fa_invalid'); // code déjà utilisé
            }
            $this->em->flush();

            return;
        }

        // 2) Code de secours — consommé à l'usage.
        if ($user->consumeBackupCode(TotpService::hashBackupCode($otp))) {
            $this->em->flush();

            return;
        }

        throw new CustomUserMessageAuthenticationException('2fa_invalid');
    }
}
