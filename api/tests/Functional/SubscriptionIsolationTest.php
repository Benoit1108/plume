<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Billing\Application\Subscriptions;
use App\Billing\Domain\SubscriptionStatus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Isolation de la table `subscription` (revue « 10/10 sécurité »).
 *
 * POURQUOI CE TEST : `subscription` est volontairement EXCLUE de la RLS (elle est écrite à
 * l'inscription publique et par les webhooks Stripe, donc HORS session tenantée — cf. ADR-0023 §4 et
 * RlsCoverageTest::EXCLUDED). Son isolation repose donc UNIQUEMENT sur un filtrage `tenant_id`
 * explicite dans le code, sans filet en base. Or c'est la table qui décide du DROIT D'ACCÈS au
 * produit (garde « lecture seule ») : une fuite ou une écriture croisée offrirait l'accès payant
 * d'un compte à un autre. Rien ne le vérifiait jusqu'ici.
 */
final class SubscriptionIsolationTest extends KernelTestCase
{
    private const string TENANT_A = '44444444-4444-4444-4444-444444444441';
    private const string TENANT_B = '44444444-4444-4444-4444-444444444442';
    private const string CUSTOMER_A = 'cus_isolation_a';
    private const string CUSTOMER_B = 'cus_isolation_b';

    private Connection $connection;
    private Subscriptions $subscriptions;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;

        $subscriptions = self::getContainer()->get(Subscriptions::class);
        \assert($subscriptions instanceof Subscriptions);
        $this->subscriptions = $subscriptions;

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    public function testEntitlementOfOneTenantNeverLeaksToAnother(): void
    {
        // A paie, B a résilié : chacun doit lire SON état, pas celui du voisin.
        $this->subscriptions->activate(self::TENANT_A, self::CUSTOMER_A, 'sub_a', null);
        $this->subscriptions->activate(self::TENANT_B, self::CUSTOMER_B, 'sub_b', null);
        $this->subscriptions->applyStatusByCustomer(self::CUSTOMER_B, SubscriptionStatus::CANCELED, null);

        self::assertTrue($this->subscriptions->isEntitled(self::TENANT_A), 'A a payé : accès ouvert');
        self::assertFalse($this->subscriptions->isEntitled(self::TENANT_B), 'B a résilié : accès fermé');
    }

    public function testSnapshotAndCustomerAreScopedToTheTenant(): void
    {
        $this->subscriptions->activate(self::TENANT_A, self::CUSTOMER_A, 'sub_a', null);
        $this->subscriptions->activate(self::TENANT_B, self::CUSTOMER_B, 'sub_b', null);

        self::assertSame(self::CUSTOMER_A, $this->subscriptions->stripeCustomerFor(self::TENANT_A));
        self::assertSame(self::CUSTOMER_B, $this->subscriptions->stripeCustomerFor(self::TENANT_B));

        // Le snapshot destiné à l'UI n'expose AUCUN identifiant Stripe (seulement `canManage`) :
        // on verrouille cette non-fuite, sinon un identifiant de facturation partirait au navigateur.
        $snapshot = $this->subscriptions->snapshot(self::TENANT_A);
        self::assertTrue($snapshot['canManage'], 'A a un client Stripe : le portail est ouvrable');
        self::assertStringNotContainsString('cus_', json_encode($snapshot, \JSON_THROW_ON_ERROR), 'aucun identifiant Stripe ne doit fuiter dans le snapshot');
    }

    public function testWebhookOnlyAffectsTheTenantOwningTheCustomer(): void
    {
        // Point le plus sensible : un webhook Stripe est identifié par `stripe_customer_id`, pas par
        // tenant. Il ne doit jamais pouvoir dégrader (ni ouvrir) l'abonnement d'un autre compte.
        $this->subscriptions->activate(self::TENANT_A, self::CUSTOMER_A, 'sub_a', null);
        $this->subscriptions->activate(self::TENANT_B, self::CUSTOMER_B, 'sub_b', null);

        $this->subscriptions->applyStatusByCustomer(self::CUSTOMER_A, SubscriptionStatus::PAST_DUE, null);

        self::assertSame(SubscriptionStatus::PAST_DUE->value, $this->statusOf(self::TENANT_A));
        self::assertSame(SubscriptionStatus::ACTIVE->value, $this->statusOf(self::TENANT_B), 'le webhook de A ne touche pas B');
    }

    public function testUnknownCustomerWebhookTouchesNobody(): void
    {
        $this->subscriptions->activate(self::TENANT_A, self::CUSTOMER_A, 'sub_a', null);

        $this->subscriptions->applyStatusByCustomer('cus_does_not_exist', SubscriptionStatus::CANCELED, null);

        self::assertSame(SubscriptionStatus::ACTIVE->value, $this->statusOf(self::TENANT_A), 'un client inconnu ne doit dégrader personne');
    }

    public function testCompAndUncompAreScopedToTheTenant(): void
    {
        // Le compte offert (back-office) ne doit pas s'étendre au voisin, ni son retrait.
        $this->subscriptions->comp(self::TENANT_A);
        $this->subscriptions->comp(self::TENANT_B);
        $this->subscriptions->uncomp(self::TENANT_A);

        self::assertSame(SubscriptionStatus::CANCELED->value, $this->statusOf(self::TENANT_A));
        self::assertSame(SubscriptionStatus::COMPED->value, $this->statusOf(self::TENANT_B), 'retirer le cadeau de A laisse celui de B intact');
        self::assertTrue($this->subscriptions->isEntitled(self::TENANT_B), 'B reste offert donc autorisé');
    }

    private function statusOf(string $tenantId): string
    {
        $status = $this->connection->fetchOne('SELECT status FROM subscription WHERE tenant_id = ?', [$tenantId]);

        return \is_string($status) ? $status : '';
    }

    private function cleanUp(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM subscription WHERE tenant_id IN (?, ?)',
            [self::TENANT_A, self::TENANT_B],
        );
    }
}
