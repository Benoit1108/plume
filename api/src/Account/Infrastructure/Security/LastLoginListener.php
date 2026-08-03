<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

use App\Account\Infrastructure\Persistence\User;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Pose `app_user.last_login_at` à chaque LOGIN RÉEL (uniquement `/api/v1/login_check`, pas les
 * rafraîchissements de token) — alimente la fiche compte du back-office. Écriture DBAL directe sur
 * une colonne hors mapping ORM (comme `created_at`) ; `app_user` est hors RLS (lu avant le tenant).
 */
final class LastLoginListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ('/api/v1/login_check' !== $event->getRequest()->getPathInfo()) {
            return;
        }
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE app_user SET last_login_at = :now WHERE email = :email',
            ['now' => $this->clock->now()->format('Y-m-d H:i:s'), 'email' => $user->getUserIdentifier()],
        );
    }
}
