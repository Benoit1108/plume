<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Import;

/**
 * Parse un CSV d'organisations en lignes normalisées.
 *
 * Tolérant : délimiteur auto-détecté (`,` `;` tabulation), en-têtes FR/EN insensibles
 * à la casse et aux accents, valeurs de type/segments mappées vers les enums,
 * pays/langues normalisés (les valeurs invalides sont abandonnées, jamais bloquantes).
 * Seul le nom est obligatoire.
 */
final class CsvOrganizationParser
{
    /** @var array<string, string> alias d'en-tête normalisé => champ canonique */
    private const HEADER_ALIASES = [
        'nom' => 'name', 'name' => 'name', 'organisation' => 'name', 'organization' => 'name',
        'societe' => 'name', 'entreprise' => 'name', 'raison sociale' => 'name',
        'type' => 'type', 'categorie' => 'type',
        'site' => 'website', 'site web' => 'website', 'siteweb' => 'website', 'website' => 'website',
        'url' => 'website', 'web' => 'website', 'lien' => 'website',
        'pays' => 'country', 'country' => 'country',
        'langues' => 'languages', 'langue' => 'languages', 'languages' => 'languages',
        'language' => 'languages', 'langues de travail' => 'languages',
        'segments' => 'segments', 'segment' => 'segments', 'domaines' => 'segments',
        'domaine' => 'segments', 'specialite' => 'segments', 'specialites' => 'segments',
        'notes' => 'notes', 'note' => 'notes', 'commentaire' => 'notes', 'commentaires' => 'notes',
        'remarque' => 'notes', 'remarques' => 'notes',
        'contact' => 'contactName', 'nom du contact' => 'contactName', 'nom contact' => 'contactName',
        'interlocuteur' => 'contactName',
        'role' => 'contactRole', 'fonction' => 'contactRole', 'poste' => 'contactRole', 'titre' => 'contactRole',
        'email' => 'contactEmail', 'e-mail' => 'contactEmail', 'mail' => 'contactEmail',
        'courriel' => 'contactEmail', 'adresse email' => 'contactEmail',
        'telephone' => 'contactPhone', 'tel' => 'contactPhone', 'phone' => 'contactPhone',
        'portable' => 'contactPhone', 'mobile' => 'contactPhone', 'numero' => 'contactPhone',
    ];

    /** @var array<string, string> valeur normalisée => OrganizationType */
    private const TYPE_MAP = [
        'publisher' => 'PUBLISHER', 'editeur' => 'PUBLISHER', 'edition' => 'PUBLISHER',
        'maison d edition' => 'PUBLISHER',
        'av_studio' => 'AV_STUDIO', 'av studio' => 'AV_STUDIO', 'labo' => 'AV_STUDIO',
        'labo av' => 'AV_STUDIO', 'labo a/v' => 'AV_STUDIO', 'studio' => 'AV_STUDIO',
        'doublage' => 'AV_STUDIO', 'sous-titrage' => 'AV_STUDIO', 'audiovisuel' => 'AV_STUDIO', 'av' => 'AV_STUDIO',
        'agency' => 'AGENCY', 'agence' => 'AGENCY', 'agence de traduction' => 'AGENCY', 'lsp' => 'AGENCY',
        'other' => 'OTHER', 'autre' => 'OTHER',
    ];

    /** @var array<string, string> jeton normalisé => Segment */
    private const SEGMENT_MAP = [
        'publishing' => 'PUBLISHING', 'edition' => 'PUBLISHING', 'livre' => 'PUBLISHING',
        'livres' => 'PUBLISHING', 'book' => 'PUBLISHING', 'books' => 'PUBLISHING',
        'audiovisual' => 'AUDIOVISUAL', 'audiovisuel' => 'AUDIOVISUAL', 'av' => 'AUDIOVISUAL',
        'sous-titrage' => 'AUDIOVISUAL', 'soustitrage' => 'AUDIOVISUAL', 'doublage' => 'AUDIOVISUAL',
        'subtitling' => 'AUDIOVISUAL',
        'technical' => 'TECHNICAL', 'technique' => 'TECHNICAL', 'tech' => 'TECHNICAL',
        'other' => 'OTHER', 'autre' => 'OTHER',
    ];

    public function parse(string $content, ?string $delimiter = null): CsvParseResult
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = trim($content);
        if ('' === $content) {
            throw new \InvalidArgumentException('Le fichier est vide.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $content, 2);
        $delimiter ??= $this->detectDelimiter(false !== $lines ? $lines[0] : $content);

        $stream = fopen('php://temp', 'r+');
        if (false === $stream) {
            throw new \RuntimeException('Impossible d\'ouvrir un flux temporaire pour le CSV.');
        }
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, null, $delimiter, '"', '');
        if (false === $header) {
            fclose($stream);
            throw new \InvalidArgumentException('En-tête introuvable.');
        }

        $columns = $this->mapHeader($header);
        if (!isset($columns['name'])) {
            fclose($stream);
            throw new \InvalidArgumentException('Colonne « nom » introuvable dans l\'en-tête.');
        }

        $rows = [];
        $errors = [];
        $line = 1;
        while (false !== ($record = fgetcsv($stream, null, $delimiter, '"', ''))) {
            ++$line;
            if ($this->isBlank($record)) {
                continue;
            }

            $value = function (string $field) use ($columns, $record): string {
                $idx = $columns[$field] ?? null;

                return null !== $idx && \array_key_exists($idx, $record) ? trim((string) $record[$idx]) : '';
            };

            $name = $value('name');
            if ('' === $name) {
                $errors[] = ['line' => $line, 'message' => 'Nom manquant, ligne ignorée.'];
                continue;
            }

            $rows[] = new ImportedRow(
                line: $line,
                name: $name,
                type: $this->mapType($value('type')),
                website: '' !== $value('website') ? $value('website') : null,
                country: $this->normalizeCountry($value('country')),
                languages: $this->normalizeLanguages($value('languages')),
                segments: $this->mapSegments($value('segments')),
                notes: '' !== $value('notes') ? $value('notes') : null,
                contactName: '' !== $value('contactName') ? $value('contactName') : null,
                contactRole: '' !== $value('contactRole') ? $value('contactRole') : null,
                contactEmail: '' !== $value('contactEmail') ? $value('contactEmail') : null,
                contactPhone: '' !== $value('contactPhone') ? $value('contactPhone') : null,
            );
        }
        fclose($stream);

        return new CsvParseResult($rows, $errors);
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * @param array<int, string|null> $header
     *
     * @return array<string, int> champ canonique => index de colonne
     */
    private function mapHeader(array $header): array
    {
        $columns = [];
        foreach ($header as $idx => $raw) {
            $key = $this->normalize((string) $raw);
            $field = self::HEADER_ALIASES[$key] ?? null;
            if (null !== $field && !isset($columns[$field])) {
                $columns[$field] = $idx;
            }
        }

        return $columns;
    }

    /** @param array<int, string|null> $record */
    private function isBlank(array $record): bool
    {
        foreach ($record as $cell) {
            if ('' !== trim((string) $cell)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        return (string) preg_replace('/\s+/', ' ', $value);
    }

    private function mapType(string $raw): string
    {
        return self::TYPE_MAP[$this->normalize($raw)] ?? 'OTHER';
    }

    /** @return string[] */
    private function mapSegments(string $raw): array
    {
        $segments = [];
        foreach ($this->tokenize($raw) as $token) {
            $segment = self::SEGMENT_MAP[$token] ?? null;
            if (null !== $segment && !\in_array($segment, $segments, true)) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /** @return string[] */
    private function normalizeLanguages(string $raw): array
    {
        $languages = [];
        foreach ($this->tokenize($raw) as $token) {
            if (1 === preg_match('/^[a-z]{2}$/', $token) && !\in_array($token, $languages, true)) {
                $languages[] = $token;
            }
        }

        return $languages;
    }

    private function normalizeCountry(string $raw): ?string
    {
        $code = strtoupper(trim($raw));

        return 1 === preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }

    /** @return string[] */
    private function tokenize(string $raw): array
    {
        $parts = preg_split('#[\s,;/|]+#', $this->normalize($raw), -1, \PREG_SPLIT_NO_EMPTY);

        return \is_array($parts) ? $parts : [];
    }
}
