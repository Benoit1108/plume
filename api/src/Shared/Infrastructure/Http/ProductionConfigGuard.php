<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Garde-fou de déploiement (V2.0-c) : en PRODUCTION uniquement, refuse de servir si des secrets
 * critiques sont restés sur le placeholder de dev (ou vides). Un `APP_SECRET` / `JWT_PASSPHRASE`
 * prévisible en prod = signatures forgeables → fail-fast explicite plutôt qu'une faille silencieuse.
 *
 * Enregistré comme listener kernel.request SEULEMENT dans `when@prod` (cf. services.yaml) ; exclu de
 * l'autowiring par défaut (arguments scalaires). Complète le fail-fast MAILBOX_ENCRYPTION_KEY
 * (ADR-0016) et la checklist docs/ops/deployment-checklist.md.
 */
final class ProductionConfigGuard
{
    /** Valeurs par défaut de dev dans api/.env — jamais acceptables en prod. */
    private const string PLACEHOLDER = 'change_me_in_env_local';
    private const string DB_PASSWORD_DEFAULT = 'plume_app';

    private bool $verified = false;

    public function __construct(
        private readonly string $appSecret,
        private readonly string $jwtPassphrase,
        private readonly string $dbPassword,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ($this->verified || !$event->isMainRequest()) {
            return;
        }

        $offenders = [];
        if ('' === $this->appSecret || self::PLACEHOLDER === $this->appSecret) {
            $offenders[] = 'APP_SECRET';
        }
        if ('' === $this->jwtPassphrase || self::PLACEHOLDER === $this->jwtPassphrase) {
            $offenders[] = 'JWT_PASSPHRASE';
        }
        if ('' === $this->dbPassword || self::DB_PASSWORD_DEFAULT === $this->dbPassword) {
            $offenders[] = 'APP_DB_PASSWORD';
        }

        if ([] !== $offenders) {
            throw new \RuntimeException(\sprintf('Production misconfiguration: %s must be overridden with a strong secret (still the dev placeholder). See docs/ops/deployment-checklist.md.', implode(', ', $offenders)));
        }

        $this->verified = true;
    }
}
