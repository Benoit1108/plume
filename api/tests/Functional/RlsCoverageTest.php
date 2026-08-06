<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou RLS (revue pré-V2, durci lors de la revue « 10/10 sécurité ») : TOUTE table métier
 * portant `tenant_id` DOIT avoir la RLS activée ET une policy d'isolation RÉELLEMENT restrictive.
 *
 * Sans ce test, une future migration qui crée une table tenantée en oubliant sa policy passerait la
 * CI (le SQLFilter applicatif masque l'absence de filet base en dev) et partirait en prod sans
 * protection RLS — le risque n°1 documenté par ADR-0023.
 *
 * On vérifie le CONTENU des policies, pas seulement leur existence : une policy `USING (true)`, sur
 * la mauvaise colonne, ou en `FOR SELECT` sans `WITH CHECK` (donc laissant écrire chez le voisin)
 * satisferait une simple assertion de présence. On vérifie aussi les attributs du rôle runtime :
 * `SUPERUSER`/`BYPASSRLS` ou la propriété d'une table rendraient toutes les policies inopérantes.
 */
final class RlsCoverageTest extends KernelTestCase
{
    /**
     * Exclusions ASSUMÉES (ADR-0023 §4) — tables tenantées écrites/lues AVANT le tenant, donc jamais RLS :
     *  - app_user     : lu au login (avant tout contexte tenant) ;
     *  - subscription : écrite à l'inscription publique (sans tenant) et par les webhooks Stripe (V2.2) ;
     *                   toujours filtrée par tenant_id EXPLICITE côté code (cf. SubscriptionIsolationTest).
     */
    private const array EXCLUDED = ['app_user', 'subscription'];

    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
    }

    public function testEveryTenantScopedTableHasRlsAndRestrictivePolicy(): void
    {
        $scoped = $this->tenantScopedTables();
        self::assertNotEmpty($scoped, 'au moins une table tenantée attendue');

        foreach ($scoped as $table) {
            $rlsEnabled = $this->connection->fetchOne(
                'SELECT relrowsecurity FROM pg_class WHERE relname = ? AND relnamespace = ?::regnamespace',
                [$table, 'public'],
            );
            self::assertTrue((bool) $rlsEnabled, \sprintf('RLS non activée sur "%s" (ENABLE ROW LEVEL SECURITY manquant ?)', $table));

            /** @var list<array{policyname: string, cmd: string, qual: string|null, with_check: string|null}> $policies */
            $policies = $this->connection->fetchAllAssociative(
                'SELECT policyname, cmd, qual, with_check FROM pg_policies WHERE schemaname = ? AND tablename = ?',
                ['public', $table],
            );
            self::assertNotEmpty($policies, \sprintf('Aucune policy RLS sur "%s"', $table));

            // Une policy couvrant TOUTES les commandes : sinon un DELETE/UPDATE non couvert
            // échapperait à l'isolation (la RLS n'interdit que ce qu'une policy encadre).
            $all = array_values(array_filter($policies, static fn (array $p): bool => 'ALL' === $p['cmd']));
            self::assertCount(1, $all, \sprintf('"%s" doit porter exactement une policy FOR ALL (trouvé : %s)', $table, implode(', ', array_map(static fn (array $p): string => $p['cmd'], $policies))));

            $policy = $all[0];
            foreach (['qual' => 'USING', 'with_check' => 'WITH CHECK'] as $column => $label) {
                $expression = $policy[$column];
                self::assertNotNull($expression, \sprintf('Policy "%s" sur "%s" : clause %s absente (écriture non contrôlée)', $policy['policyname'], $table, $label));
                self::assertStringContainsString('tenant_id', $expression, \sprintf('Policy "%s" sur "%s" : %s ne filtre pas sur tenant_id (%s)', $policy['policyname'], $table, $label, $expression));
                self::assertStringContainsString('app.current_tenant', $expression, \sprintf('Policy "%s" sur "%s" : %s ne compare pas à la variable de session du tenant (%s)', $policy['policyname'], $table, $label, $expression));
            }
        }
    }

    public function testRuntimeRoleCannotBypassRls(): void
    {
        $runtimeRole = \is_string($_ENV['APP_DB_USER'] ?? null) ? $_ENV['APP_DB_USER'] : 'plume_app';

        /** @var array{rolsuper: bool, rolbypassrls: bool}|false $role */
        $role = $this->connection->fetchAssociative(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = ?',
            [$runtimeRole],
        );
        self::assertNotFalse($role, \sprintf('Rôle runtime "%s" absent (make provision-app-role ?)', $runtimeRole));
        self::assertFalse((bool) $role['rolsuper'], \sprintf('"%s" est SUPERUSER : il contournerait toutes les policies', $runtimeRole));
        self::assertFalse((bool) $role['rolbypassrls'], \sprintf('"%s" a BYPASSRLS : il contournerait toutes les policies', $runtimeRole));

        // Le PROPRIÉTAIRE d'une table contourne la RLS tant qu'elle n'est pas en FORCE : le rôle
        // runtime ne doit posséder aucune table tenantée.
        /** @var list<string> $owned */
        $owned = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT c.relname
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind = 'r' AND pg_get_userbyid(c.relowner) = ?
                SQL,
            [$runtimeRole],
        );
        self::assertSame([], $owned, \sprintf('"%s" possède des tables (%s) : le propriétaire contourne la RLS', $runtimeRole, implode(', ', $owned)));
    }

    /** @return list<string> */
    private function tenantScopedTables(): array
    {
        /** @var list<string> $tables */
        $tables = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT c.relname
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public'
                  AND c.relkind = 'r'
                  AND EXISTS (
                      SELECT 1 FROM information_schema.columns col
                      WHERE col.table_schema = 'public' AND col.table_name = c.relname AND col.column_name = 'tenant_id'
                  )
                SQL,
        );

        return array_values(array_filter($tables, static fn (string $t): bool => !\in_array($t, self::EXCLUDED, true)));
    }
}
