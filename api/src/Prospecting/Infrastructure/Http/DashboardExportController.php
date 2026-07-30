<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Http;

use App\Prospecting\Application\Query\GetDashboard\GetDashboard;
use App\Prospecting\Application\ReadModel\DashboardView;
use App\Shared\Application\Query\QueryBus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * Export CSV du tableau de bord (GET /api/v1/dashboard/export) : mêmes chiffres que l'écran, à
 * emporter dans un tableur. Lit le read model (fail-closed tenant) via le QueryBus, aucune donnée
 * hors tenant. Libellés de statut par CODE (stables) — l'export ne dépend pas des libellés custom.
 */
#[AsController]
final class DashboardExportController
{
    public function __construct(private readonly QueryBus $queryBus)
    {
    }

    public function __invoke(): Response
    {
        /** @var DashboardView $view */
        $view = $this->queryBus->ask(new GetDashboard());

        $csv = fopen('php://temp', 'r+');
        \assert(false !== $csv);
        // PHP 8.5 : `$escape` doit être fourni explicitement ('' = plus d'échappement legacy, CSV propre).
        $sep = ',';
        $enc = '"';
        $esc = '';

        fputcsv($csv, ['Indicateur', 'Valeur'], $sep, $enc, $esc);
        fputcsv($csv, ['Pistes contactées', $view->contacted], $sep, $enc, $esc);
        fputcsv($csv, ['Pistes ayant répondu', $view->replied], $sep, $enc, $esc);
        fputcsv($csv, ['Gagnées', $view->won], $sep, $enc, $esc);
        fputcsv($csv, ['Perdues', $view->lost], $sep, $enc, $esc);
        fputcsv($csv, ['Pistes actives', $view->activeLeads], $sep, $enc, $esc);
        fputcsv($csv, ['Actes de démarchage (mois en cours)', $view->outreachThisMonth], $sep, $enc, $esc);
        fputcsv($csv, ['Objectif hebdomadaire', $view->weeklyTarget], $sep, $enc, $esc);
        fputcsv($csv, ['Délai moyen 1re réponse (jours)', $view->firstResponseDelayDays ?? ''], $sep, $enc, $esc);
        fputcsv($csv, ['Valeur estimée du pipeline (€)', $view->pipelineValue], $sep, $enc, $esc);
        fputcsv($csv, ['Valeur estimée gagnée (€)', $view->wonValue], $sep, $enc, $esc);

        fputcsv($csv, ['', ''], $sep, $enc, $esc);
        fputcsv($csv, ['Statut', 'Nombre'], $sep, $enc, $esc);
        foreach ($view->pipeline as $slice) {
            fputcsv($csv, [$slice->status, $slice->count], $sep, $enc, $esc);
        }

        fputcsv($csv, ['', ''], $sep, $enc, $esc);
        fputcsv($csv, ['Segment', 'Contactées', 'Réponses', 'Gagnées'], $sep, $enc, $esc);
        foreach ($view->segments as $stats) {
            fputcsv($csv, [$stats->segment, $stats->contacted, $stats->replied, $stats->won], $sep, $enc, $esc);
        }

        rewind($csv);
        $body = (string) stream_get_contents($csv);
        fclose($csv);

        // BOM UTF-8 : Excel ouvre l'accentué correctement (comme l'export RGPD).
        return new Response("\u{FEFF}".$body, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plume-tableau-de-bord.csv"',
        ]);
    }
}
