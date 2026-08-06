<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Infrastructure\ReadModel\AccountDirectory;
use App\Shared\Infrastructure\Export\CsvCell;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Back-office — export CSV des comptes (GET /api/v1/admin/accounts/export, ROLE_ADMIN). Mêmes
 * données et mêmes filtres que la liste (source `AccountDirectory`), à emporter dans un tableur.
 * Minimisation : comptages seulement, jamais de contenu métier. Borné (5000 lignes).
 */
#[AsController]
final class AdminAccountsExportController
{
    public function __construct(private readonly AccountDirectory $accounts)
    {
    }

    public function __invoke(Request $request): Response
    {
        $rows = $this->accounts->list(
            trim((string) $request->query->get('q', '')),
            (string) $request->query->get('status', 'all'),
            (string) $request->query->get('sort', 'email'),
            5000,
        );

        $csv = fopen('php://temp', 'r+');
        \assert(false !== $csv);
        $sep = ',';
        $enc = '"';
        $esc = '';

        fputcsv($csv, ['Email', 'Vérifié', 'En suppression', 'Créé le', 'Organisations', 'Pistes', 'Boîte'], $sep, $enc, $esc);
        foreach ($rows as $row) {
            // Neutralisation des formules : l'email vient d'une inscription publique (revue P3).
            fputcsv($csv, CsvCell::safeRow([
                $row['email'],
                $row['emailVerified'] ? 'oui' : 'non',
                null !== $row['deletionRequestedAt'] ? 'oui' : 'non',
                $row['createdAt'] ?? '',
                $row['organizations'],
                $row['leads'],
                $row['mailboxStatus'],
            ]), $sep, $enc, $esc);
        }

        rewind($csv);
        $body = (string) stream_get_contents($csv);
        fclose($csv);

        // BOM UTF-8 : Excel ouvre l'accentué correctement (comme les autres exports).
        return new Response("\u{FEFF}".$body, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plume-comptes.csv"',
        ]);
    }
}
