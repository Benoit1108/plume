<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Auth;

use App\Shared\Application\Clock;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Hygiène des sessions (lot « densité ») : à chaque authentification réussie — connexion comme
 * rafraîchissement — on ferme les sessions EXPIRÉES du compte et on plafonne le nombre de sessions
 * vivantes.
 *
 * Sans ça la table gagne une ligne par connexion, valable 30 jours : la page Compte finit avec des
 * dizaines de sessions et le conseil « révoquez ce que vous ne reconnaissez pas » devient
 * intenable. La purge quotidienne `gesdinet:jwt:clear` ne traite QUE les tokens expirés, jamais
 * l'accumulation de sessions valides.
 *
 * Priorité négative : le listener gesdinet (priorité 0) vient de créer la session courante — elle
 * est donc la plus récente et survit toujours au plafond.
 */
final class PruneSessionsListener
{
    /** Un compte légitime cumule quelques appareils, pas dix — au-delà, on ferme les plus anciennes. */
    private const int MAX_LIVE_SESSIONS = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Clock $clock,
    ) {
    }

    #[AsEventListener(event: Events::AUTHENTICATION_SUCCESS, priority: -10)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $username = $event->getUser()->getUserIdentifier();

        $this->em->createQuery(
            \sprintf('DELETE FROM %s t WHERE t.username = :username AND t.valid < :now', RefreshToken::class)
        )
            ->setParameter('username', $username)
            ->setParameter('now', $this->clock->now())
            ->execute();

        /** @var list<int> $ids — les plus récentes d'abord (l'id est séquentiel). */
        $ids = $this->em->createQuery(
            \sprintf('SELECT t.id FROM %s t WHERE t.username = :username ORDER BY t.id DESC', RefreshToken::class)
        )
            ->setParameter('username', $username)
            ->getSingleColumnResult();

        $excess = \array_slice($ids, self::MAX_LIVE_SESSIONS);
        if ([] === $excess) {
            return;
        }

        $this->em->createQuery(
            \sprintf('DELETE FROM %s t WHERE t.id IN (:ids)', RefreshToken::class)
        )
            ->setParameter('ids', $excess)
            ->execute();
    }
}
