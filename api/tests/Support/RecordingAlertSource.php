<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Sourcing\Application\Source\AlertSource;
use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Domain\CandidateLead\Source;

/** Source de test : une annonce par URL demandée ; retient les URLs sollicitées. */
final class RecordingAlertSource implements AlertSource
{
    /** @var list<string> */
    public array $fetchedUrls = [];

    public function fetch(string $feedUrl): iterable
    {
        $this->fetchedUrls[] = $feedUrl;

        // externalId = l'URL → une annonce distincte par flux, re-relève dédoublonnée.
        yield new ParsedAlert(Source::RSS->value, 'Annonce de '.$feedUrl, null, null, $feedUrl, null, $feedUrl);
    }
}
