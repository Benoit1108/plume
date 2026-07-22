<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Point unique du tenant : `activate` pose contexte + filtre Doctrine, `clear` remet à zéro. */
final class TenantScopeTest extends KernelTestCase
{
    public function testActivateSetsContextAndEnablesTheFilterThenClearResetsBoth(): void
    {
        $c = static::getContainer();
        $scope = $c->get(TenantScope::class);
        $context = $c->get(TenantContext::class);
        $em = $c->get(EntityManagerInterface::class);
        \assert($scope instanceof TenantScope);
        \assert($context instanceof TenantContext);
        \assert($em instanceof EntityManagerInterface);

        $scope->clear(); // ardoise propre (le test porte sur les transitions activate/clear)

        $scope->activate(TenantId::fromString('11111111-1111-1111-1111-111111111111'));
        $active = $context->get();
        self::assertNotNull($active, 'le contexte porte le tenant après activate');
        self::assertSame('11111111-1111-1111-1111-111111111111', $active->toString());
        self::assertTrue($em->getFilters()->isEnabled('tenant'), 'le filtre tenant est actif après activate');

        $scope->clear();
        self::assertNull($context->get(), 'le contexte est vidé après clear');
        self::assertFalse($em->getFilters()->isEnabled('tenant'), 'le filtre est désactivé après clear');
    }
}
