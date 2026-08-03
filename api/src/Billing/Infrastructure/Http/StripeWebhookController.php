<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Billing\Application\Subscriptions;
use App\Billing\Domain\SubscriptionStatus;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * V2.2 — webhook Stripe (POST /api/v1/billing/webhook, PUBLIC). SEULE source de vérité pour créditer
 * l'accès : la signature `Stripe-Signature` est vérifiée (HMAC-SHA256, anti-rejeu par tolérance de
 * temps) avant tout traitement. Traduit les événements d'abonnement en état local (active/impayé/
 * résilié). Répond toujours 200 aux événements valides (même ignorés) pour éviter les retries ;
 * 400 sur signature/configuration invalide.
 *
 * DÉDUPLICATION (revue santé S-P2c) : chaque `event.id` traité est mémorisé (`stripe_webhook_event`)
 * et un événement déjà vu est ignoré — un payload signé rejoué dans la fenêtre de tolérance ne
 * re-crédite plus l'accès. Le marquage n'a lieu qu'APRÈS traitement (les upserts sont idempotents,
 * donc un échec avant marquage laisse Stripe rejouer sans risque de sauter l'événement).
 */
#[AsController]
final class StripeWebhookController
{
    private const int TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly Clock $clock,
        private readonly Connection $connection,
        private readonly string $webhookSecret,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        if (!$this->signatureIsValid($raw, $request->headers->get('Stripe-Signature', ''))) {
            return new JsonResponse(['detail' => 'invalid_signature'], Response::HTTP_BAD_REQUEST);
        }

        $event = json_decode($raw, true);
        $eventId = \is_array($event) && \is_string($event['id'] ?? null) ? $event['id'] : '';
        if ('' !== $eventId && $this->alreadyProcessed($eventId)) {
            return new JsonResponse(['received' => true, 'duplicate' => true]); // rejeu ignoré
        }

        $type = \is_array($event) && \is_string($event['type'] ?? null) ? $event['type'] : '';
        $data = \is_array($event) ? ($event['data'] ?? null) : null;
        $object = \is_array($data) && \is_array($data['object'] ?? null) ? $data['object'] : [];

        match ($type) {
            'checkout.session.completed' => $this->onCheckoutCompleted($object),
            'customer.subscription.updated' => $this->onSubscriptionChanged($object),
            'customer.subscription.deleted' => $this->applyStatus($object, SubscriptionStatus::CANCELED),
            default => null, // événement non suivi : accusé de réception, aucun effet
        };

        if ('' !== $eventId) {
            $this->markProcessed($eventId);
        }

        return new JsonResponse(['received' => true]);
    }

    private function alreadyProcessed(string $eventId): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM stripe_webhook_event WHERE event_id = :id',
            ['id' => $eventId],
        );
    }

    private function markProcessed(string $eventId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO stripe_webhook_event (event_id, processed_at) VALUES (:id, :now) ON CONFLICT (event_id) DO NOTHING',
            ['id' => $eventId, 'now' => $this->clock->now()->format('Y-m-d H:i:s')],
        );
    }

    /** @param array<array-key, mixed> $object */
    private function onCheckoutCompleted(array $object): void
    {
        $tenantId = \is_string($object['client_reference_id'] ?? null) ? $object['client_reference_id'] : '';
        $customer = \is_string($object['customer'] ?? null) ? $object['customer'] : '';
        $subscription = \is_string($object['subscription'] ?? null) ? $object['subscription'] : '';
        if ('' === $tenantId || '' === $customer || '' === $subscription) {
            return;
        }

        $this->subscriptions->activate($tenantId, $customer, $subscription, null);
    }

    /** @param array<array-key, mixed> $object */
    private function onSubscriptionChanged(array $object): void
    {
        $status = match (\is_string($object['status'] ?? null) ? $object['status'] : '') {
            'active', 'trialing' => SubscriptionStatus::ACTIVE,
            'past_due' => SubscriptionStatus::PAST_DUE,
            'canceled', 'unpaid', 'incomplete_expired' => SubscriptionStatus::CANCELED,
            default => null,
        };
        if (null !== $status) {
            $this->applyStatus($object, $status);
        }
    }

    /** @param array<array-key, mixed> $object */
    private function applyStatus(array $object, SubscriptionStatus $status): void
    {
        $customer = \is_string($object['customer'] ?? null) ? $object['customer'] : '';
        if ('' === $customer) {
            return;
        }
        $periodEnd = is_numeric($object['current_period_end'] ?? null)
            ? (new \DateTimeImmutable())->setTimestamp((int) $object['current_period_end'])
            : null;

        $this->subscriptions->applyStatusByCustomer($customer, $status, $periodEnd);
    }

    private function signatureIsValid(string $payload, string $header): bool
    {
        if ('' === trim($this->webhookSecret) || '' === $header) {
            return false; // webhook non configuré ou en-tête absent → refus
        }

        $timestamp = null;
        $signature = null;
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ('t' === $key) {
                $timestamp = $value;
            } elseif ('v1' === $key) {
                $signature = $value;
            }
        }
        if (null === $timestamp || null === $signature || !ctype_digit($timestamp)) {
            return false;
        }
        if (abs($this->clock->now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false; // anti-rejeu : événement trop ancien
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
