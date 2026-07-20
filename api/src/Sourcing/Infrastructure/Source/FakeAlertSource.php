<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Source;

use App\Sourcing\Application\Source\AlertSource;
use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Domain\CandidateLead\Source;

/**
 * Source factice déterministe (défaut sans `SOURCING_RSS_FEED_URL`) : joue la boucle
 * d'ingestion de bout en bout sans réseau (dev / CI / E2E). Les `externalId` sont FIXES →
 * une relève répétée est dédoublonnée (aucun doublon), donc idempotente et testable.
 */
final class FakeAlertSource implements AlertSource
{
    public function fetch(string $feedUrl): iterable
    {
        yield new ParsedAlert(
            source: Source::RSS->value,
            title: 'Traducteur EN>FR — série documentaire (sous-titrage)',
            organizationName: 'Studio Démo Audiovisuel',
            languagePair: 'en>fr',
            url: 'https://example.test/annonces/av-doc-en-fr',
            excerpt: 'Recherche traducteur·rice pour le sous-titrage d\'une série documentaire.',
            externalId: 'demo-rss-av-1',
            postedAt: '2026-07-18T09:00:00+00:00',
            rawPayload: '<item><guid>demo-rss-av-1</guid><title>Traducteur EN&gt;FR — série documentaire</title></item>',
        );

        yield new ParsedAlert(
            source: Source::RSS->value,
            title: 'Traduction littéraire ES>FR — roman jeunesse',
            organizationName: 'Éditions Démo',
            languagePair: 'es>fr',
            url: 'https://example.test/annonces/edition-es-fr',
            excerpt: 'Projet de traduction d\'un roman jeunesse de l\'espagnol vers le français.',
            externalId: 'demo-rss-edition-1',
            postedAt: '2026-07-17T14:30:00+00:00',
            rawPayload: '<item><guid>demo-rss-edition-1</guid><title>Traduction littéraire ES&gt;FR</title></item>',
        );
    }
}
