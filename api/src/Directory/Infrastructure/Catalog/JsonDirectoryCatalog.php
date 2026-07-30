<?php

declare(strict_types=1);

namespace App\Directory\Infrastructure\Catalog;

use App\Directory\Application\Catalog\CatalogEntry;
use App\Directory\Application\Catalog\DirectoryCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Annuaire suggéré lu depuis un fichier JSON livré avec l'app (`data/directory-catalog.json`) — Benoit
 * l'enrichit sans toucher au code. Lecture TOLÉRANTE : fichier absent/illisible/entrée mal formée →
 * ignorés (jamais d'erreur), l'annuaire n'est qu'une aide. Résultat mémoïsé (le fichier ne change pas
 * en cours de process).
 */
final class JsonDirectoryCatalog implements DirectoryCatalog
{
    /** @var CatalogEntry[]|null */
    private ?array $cache = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/directory-catalog.json')]
        private readonly string $path,
    ) {
    }

    public function all(): array
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $entries = [];
        if (is_file($this->path)) {
            $raw = file_get_contents($this->path);
            $decoded = \is_string($raw) ? json_decode($raw, true) : null;
            if (\is_array($decoded) && isset($decoded['entries']) && \is_array($decoded['entries'])) {
                foreach ($decoded['entries'] as $row) {
                    $entry = self::toEntry($row);
                    if (null !== $entry) {
                        $entries[] = $entry;
                    }
                }
            }
        }

        return $this->cache = $entries;
    }

    public function find(string $id): ?CatalogEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    private static function toEntry(mixed $row): ?CatalogEntry
    {
        if (!\is_array($row) || !\is_string($row['id'] ?? null) || !\is_string($row['name'] ?? null) || !\is_string($row['type'] ?? null)) {
            return null;
        }

        return new CatalogEntry(
            $row['id'],
            $row['name'],
            $row['type'],
            \is_string($row['country'] ?? null) ? $row['country'] : null,
            \is_string($row['website'] ?? null) ? $row['website'] : null,
            self::stringList($row['languages'] ?? null),
            self::stringList($row['segments'] ?? null),
            \is_string($row['note'] ?? null) ? $row['note'] : null,
        );
    }

    /** @return string[] */
    private static function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $v): bool => \is_string($v)));
    }
}
