<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Source;

use App\Sourcing\Application\Source\AlertSource;

/**
 * Choix de la source (patron M2 « réel si configuré, factice sinon ») : `RssAlertSource`
 * dès qu'un flux `SOURCING_RSS_FEED_URL` est fourni, sinon `FakeAlertSource` (démo, sans réseau).
 */
final class AlertSourceFactory
{
    public function __construct(
        private readonly RssAlertSource $rss,
        private readonly FakeAlertSource $fake,
        private readonly string $feedUrl,
    ) {
    }

    public function create(): AlertSource
    {
        return '' !== trim($this->feedUrl) ? $this->rss : $this->fake;
    }
}
