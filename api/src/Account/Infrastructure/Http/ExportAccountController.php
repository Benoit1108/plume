<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use App\Shared\Application\Clock;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * RGPD — portabilité : export de TOUTES les données du tenant courant dans une archive ZIP
 * (export.json complet + organisations.csv et pistes.csv lisibles dans un tableur). Synchrone
 * (volume borné en « 1 compte = 1 traductrice »).
 *
 * Deux lignes de défense pour ne JAMAIS sortir les données d'un autre compte : on ne dumpe que les
 * tables dont la RLS est activée (fail-closed : `app_user`, non protégée, n'en est jamais) ET on
 * filtre explicitement sur `tenant_id` (prédicat direct, ADR-0013). Les colonnes SECRÈTES (tokens
 * OAuth chiffrés, curseur de sync) et le brut d'annonces (contenu de tiers, rétention limitée) ne
 * sont jamais exportés.
 */
#[AsController]
final class ExportAccountController
{
    /** Colonnes jamais exportées (secrets/credentials). */
    private const array SECRET_COLUMNS = ['access_token', 'refresh_token', 'sync_cursor'];

    /** Tables tenantées exclues de l'export (brut de tiers, à rétention limitée). */
    private const array EXCLUDED_TABLES = ['raw_alert'];

    public function __construct(
        private readonly Security $security,
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('ExportAccountController behind the firewall: a user is always present.');
        }
        $tenant = $this->tenantContext->require()->toString();

        $data = [];
        foreach ($this->exportableTables() as $table) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $this->connection->fetchAllAssociative(
                \sprintf('SELECT * FROM %s WHERE tenant_id = :tenant', $this->connection->quoteIdentifier($table)),
                ['tenant' => $tenant],
            );
            $data[$table] = array_map(
                static fn (array $row): array => array_diff_key($row, array_flip(self::SECRET_COLUMNS)),
                $rows,
            );
        }

        $export = [
            'compte' => ['email' => $user->getUserIdentifier(), 'exportedAt' => $this->clock->now()->format(\DATE_ATOM)],
            'donnees' => $data,
        ];

        $zipPath = tempnam(sys_get_temp_dir(), 'plume-export-');
        if (false === $zipPath) {
            throw new \RuntimeException('Cannot create a temporary file for the export.');
        }

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('export.json', (string) json_encode($export, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
        $zip->addFromString('organisations.csv', $this->organizationsCsv($data['organization'] ?? []));
        $zip->addFromString('pistes.csv', $this->leadsCsv($data['lead'] ?? [], $data['organization'] ?? []));
        $zip->close();

        $response = new Response((string) file_get_contents($zipPath), Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                \sprintf('plume-export-%s.zip', $this->clock->now()->format('Y-m-d')),
            ),
        ]);
        @unlink($zipPath);

        return $response;
    }

    /**
     * Tables sûres à exporter : RLS activée (jamais `app_user`) ET portant `tenant_id`, hors exclusions.
     *
     * @return list<string>
     */
    private function exportableTables(): array
    {
        /** @var list<string> $tables */
        $tables = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT c.relname
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'public' AND c.relkind = 'r' AND c.relrowsecurity = true
                  AND EXISTS (
                      SELECT 1 FROM information_schema.columns col
                      WHERE col.table_schema = 'public' AND col.table_name = c.relname AND col.column_name = 'tenant_id'
                  )
                ORDER BY c.relname
                SQL,
        );

        return array_values(array_filter($tables, static fn (string $t): bool => !\in_array($t, self::EXCLUDED_TABLES, true)));
    }

    /**
     * @param list<array<string, mixed>> $organizations
     */
    private function organizationsCsv(array $organizations): string
    {
        $rows = array_map(static fn (array $o): array => [
            self::str($o, 'name'),
            self::str($o, 'type'),
            self::str($o, 'website'),
            self::str($o, 'country'),
            self::str($o, 'notes'),
        ], $organizations);

        return self::csv(['Nom', 'Type', 'Site web', 'Pays', 'Notes'], $rows);
    }

    /**
     * @param list<array<string, mixed>> $leads
     * @param list<array<string, mixed>> $organizations
     */
    private function leadsCsv(array $leads, array $organizations): string
    {
        $orgName = [];
        foreach ($organizations as $o) {
            $orgName[self::str($o, 'id')] = self::str($o, 'name');
        }

        $rows = array_map(static fn (array $l): array => [
            $orgName[self::str($l, 'organization_id')] ?? '',
            self::str($l, 'segment'),
            self::str($l, 'status'),
            self::str($l, 'priority'),
            self::str($l, 'language_pair'),
            self::str($l, 'source'),
            self::str($l, 'created_at'),
            self::str($l, 'last_contacted_at'),
            self::str($l, 'next_follow_up_at'),
        ], $leads);

        return self::csv(
            ['Organisation', 'Segment', 'Statut', 'Priorité', 'Langues', 'Source', 'Créée le', 'Dernier contact', 'Relance prévue'],
            $rows,
        );
    }

    /**
     * @param list<string>       $header
     * @param list<list<string>> $rows
     */
    private static function csv(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if (false === $handle) {
            throw new \RuntimeException('Cannot open a temporary stream for CSV export.');
        }
        // Escape vide : pas de déséchappement backslash (recommandé, PHP 8.4+).
        fputcsv($handle, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        // BOM UTF-8 : Excel reconnaît alors l'encodage (accents corrects).
        return "\u{FEFF}".$csv;
    }

    /** @param array<string, mixed> $row */
    private static function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }
}
