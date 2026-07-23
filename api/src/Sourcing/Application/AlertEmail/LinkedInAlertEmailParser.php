<?php

declare(strict_types=1);

namespace App\Sourcing\Application\AlertEmail;

use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Domain\CandidateLead\Source;

/**
 * Parser fin des alertes emploi LinkedIn (expéditeur `linkedin.com`). Un email est un DIGEST : plusieurs
 * offres, chacune sous la forme d'un bloc `Titre / Entreprise / Lieu [/ badge] / lien jobs/view`.
 *
 * On s'ancre sur les liens `.../jobs/view/<id>` (fiables et uniques par offre) et on remonte les
 * lignes de contenu du bloc pour titre/entreprise/lieu. L'`externalId` est l'id LinkedIn de
 * l'offre (dédoublonnage : la même offre revue dans un digest ultérieur n'est pas réingérée ;
 * deux offres d'un même email restent deux candidats). L'URL stockée est la forme CANONIQUE
 * publique (sans les jetons de tracking du mail). Best-effort : si rien n'est reconnu → `[]`
 * (le parser générique prend le relais).
 */
final class LinkedInAlertEmailParser implements ProviderAlertParser
{
    private const int MAX_TITLE = 300;
    private const int MAX_ORG = 200;
    private const int MAX_LOCATION = 200;
    private const int MAX_CONTENT_LINES = 3; // titre, entreprise, lieu

    public function supports(string $fromAddress): bool
    {
        return str_contains(mb_strtolower($fromAddress), 'linkedin.');
    }

    public function parse(string $subject, string $body, string $externalId): array
    {
        $lines = $this->contentLines($body);
        $alerts = [];
        $seen = [];

        foreach ($lines as $i => $line) {
            $jobId = $this->jobId($line);
            if (null === $jobId || isset($seen[$jobId])) {
                continue;
            }
            $seen[$jobId] = true;

            $block = $this->blockAbove($lines, $i);
            $title = $this->clean($block[0] ?? '', self::MAX_TITLE);
            if ('' === $title) {
                continue; // pas de titre exploitable → on saute cette offre
            }
            $company = $this->clean($block[1] ?? '', self::MAX_ORG);
            $location = $this->clean($block[2] ?? '', self::MAX_LOCATION);

            $alerts[] = new ParsedAlert(
                source: Source::LINKEDIN->value,
                title: $title,
                organizationName: '' !== $company ? $company : null,
                languagePair: null,
                url: 'https://www.linkedin.com/jobs/view/'.$jobId,
                excerpt: '' !== $location ? $location : null,
                externalId: 'linkedin-'.$jobId,
                postedAt: null,
                rawPayload: trim(implode("\n", array_filter([$title, $company, $location]))),
            );
        }

        return $alerts;
    }

    /**
     * Lignes non vides, nettoyées des espaces.
     *
     * @return list<string>
     */
    private function contentLines(string $body): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim((string) $line);
            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function jobId(string $line): ?string
    {
        return 1 === preg_match('#linkedin\.com/[^\s]*?jobs/view/(\d+)#i', $line, $m) ? $m[1] : null;
    }

    /**
     * Remonte depuis la ligne du lien pour récupérer, dans l'ordre [titre, entreprise, lieu],
     * jusqu'à 3 lignes de contenu — en sautant les badges et en s'arrêtant à une frontière
     * (séparateur, autre offre, phrase structurelle intro/pied de page).
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function blockAbove(array $lines, int $urlIndex): array
    {
        $collected = []; // du plus proche du lien au plus lointain
        for ($j = $urlIndex - 1; $j >= 0 && \count($collected) < self::MAX_CONTENT_LINES; --$j) {
            $line = $lines[$j];
            if ($this->isBoundary($line)) {
                break;
            }
            if ($this->isSkippable($line)) {
                continue;
            }
            $collected[] = $line;
        }

        return array_reverse($collected); // [titre, entreprise, lieu]
    }

    /** Frontière de bloc : on arrête la remontée (ne pas confondre avec le contenu d'une offre). */
    private function isBoundary(string $line): bool
    {
        if (1 === preg_match('/^[-=_*]{3,}$/', $line)) {
            return true;
        }
        if (null !== $this->jobId($line)) {
            return true; // début de l'offre précédente
        }
        $low = mb_strtolower($line);
        foreach (['votre alerte', 'vous recevrez', 'voir toutes les offres', 'cet e-mail', 'gérez vos alertes', 'linkedin ireland', 'se désabonner'] as $marker) {
            if (str_contains($low, $marker)) {
                return true;
            }
        }

        return false;
    }

    /** Ligne à ignorer mais qui ne borne pas le bloc (badges LinkedIn). */
    private function isSkippable(string $line): bool
    {
        $low = mb_strtolower($line);

        return str_contains($low, 'recrute activement')
            || str_contains($low, 'actively recruiting')
            || str_contains($low, 'voir l’offre')
            || str_contains($low, "voir l'offre")
            || str_contains($low, 'view job');
    }

    private function clean(string $value, int $max): string
    {
        $value = trim(html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

        return mb_substr($value, 0, $max);
    }
}
