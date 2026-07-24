<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure;

use App\Account\Infrastructure\Persistence\User;
use App\Account\Infrastructure\Security\AccountStatusChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Uid\Uuid;

/**
 * Un compte marqué pour suppression (soft-delete) est refusé à l'authentification ; un compte normal
 * (ou un autre type d'utilisateur) passe sans entrave.
 */
final class AccountStatusCheckerTest extends TestCase
{
    public function testRejectsUserScheduledForDeletion(): void
    {
        $user = new User(Uuid::v7(), Uuid::v7(), 'gone@plume.test');
        $user->requestDeletion(new \DateTimeImmutable('2026-07-24T10:00:00'));

        $this->expectException(CustomUserMessageAccountStatusException::class);
        (new AccountStatusChecker())->checkPreAuth($user);
    }

    public function testAllowsActiveUser(): void
    {
        $user = new User(Uuid::v7(), Uuid::v7(), 'active@plume.test');

        (new AccountStatusChecker())->checkPreAuth($user);
        $this->addToAssertionCount(1);
    }

    public function testIgnoresOtherUserTypes(): void
    {
        (new AccountStatusChecker())->checkPreAuth(new InMemoryUser('x@plume.test', null));
        $this->addToAssertionCount(1);
    }
}
