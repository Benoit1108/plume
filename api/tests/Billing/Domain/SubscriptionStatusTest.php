<?php

declare(strict_types=1);

namespace App\Tests\Billing\Domain;

use App\Billing\Domain\SubscriptionStatus;
use PHPUnit\Framework\TestCase;

/** La règle d'accès en écriture selon le statut (+ validité de l'essai). */
final class SubscriptionStatusTest extends TestCase
{
    public function testActiveAndCompedAlwaysGrantAccess(): void
    {
        self::assertTrue(SubscriptionStatus::ACTIVE->grantsWriteAccess(false));
        self::assertTrue(SubscriptionStatus::COMPED->grantsWriteAccess(false));
    }

    public function testTrialingGrantsAccessOnlyWhileValid(): void
    {
        self::assertTrue(SubscriptionStatus::TRIALING->grantsWriteAccess(true));
        self::assertFalse(SubscriptionStatus::TRIALING->grantsWriteAccess(false)); // essai expiré
    }

    public function testPastDueAndCanceledNeverGrantAccess(): void
    {
        self::assertFalse(SubscriptionStatus::PAST_DUE->grantsWriteAccess(true));
        self::assertFalse(SubscriptionStatus::CANCELED->grantsWriteAccess(true));
    }
}
