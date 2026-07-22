<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Infrastructure;

use App\Sourcing\Application\Source\ParsedAlert;
use App\Sourcing\Infrastructure\Source\RssAlertSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RssAlertSourceTest extends TestCase
{
    private const string FEED = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0">
          <channel>
            <title>Annonces démo</title>
            <item>
              <title>Traducteur EN&gt;FR — documentaire</title>
              <link>https://example.test/jobs/1</link>
              <guid>job-0001</guid>
              <description>Sous-titrage d'une &lt;b&gt;série&lt;/b&gt; documentaire.</description>
              <pubDate>Sat, 18 Jul 2026 09:00:00 +0000</pubDate>
            </item>
            <item>
              <title>   </title>
              <link>https://example.test/jobs/broken</link>
            </item>
            <item>
              <title>Traduction littéraire ES&gt;FR</title>
              <guid>job-0002</guid>
            </item>
          </channel>
        </rss>
        XML;

    // Flux ATOM (feed>entry) : l'ancien parser maison renvoyait ZÉRO ici (il ne lisait que
    // channel->item). laminas-feed le lit derrière la même interface.
    private const string ATOM = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
          <title>Alertes Atom</title>
          <entry>
            <title>Relecture FR — roman graphique</title>
            <link href="https://example.test/atom/1"/>
            <id>atom-0001</id>
            <summary>Relecture d'un &lt;i&gt;roman&lt;/i&gt; graphique.</summary>
            <updated>2026-07-19T10:30:00Z</updated>
          </entry>
        </feed>
        XML;

    /** @return list<ParsedAlert> */
    private function fetch(MockHttpClient $client, string $url = 'https://feed.test/rss'): array
    {
        return iterator_to_array((new RssAlertSource($client))->fetch($url), false);
    }

    public function testParsesValidItemsAndSkipsTitleless(): void
    {
        $alerts = $this->fetch(new MockHttpClient(new MockResponse(self::FEED)));

        self::assertCount(2, $alerts); // l'item sans titre est ignoré (best-effort).

        $first = $alerts[0];
        self::assertSame('Traducteur EN>FR — documentaire', $first->title);
        self::assertSame('job-0001', $first->externalId);
        self::assertSame('https://example.test/jobs/1', $first->url);
        self::assertSame('RSS', $first->source);
        self::assertStringContainsString('série documentaire', (string) $first->excerpt); // balises retirées
        self::assertSame('2026-07-18T09:00:00+00:00', $first->postedAt);
        self::assertStringContainsString('job-0001', (string) $first->rawPayload);

        // Sans guid : l'externalId retombe sur le lien (ici absent) => null, mais le titre suffit.
        self::assertSame('job-0002', $alerts[1]->externalId);
    }

    public function testParsesAtomFeedEntries(): void
    {
        $alerts = $this->fetch(new MockHttpClient(new MockResponse(self::ATOM)));

        self::assertCount(1, $alerts);
        self::assertSame('Relecture FR — roman graphique', $alerts[0]->title);
        self::assertSame('atom-0001', $alerts[0]->externalId);
        self::assertSame('https://example.test/atom/1', $alerts[0]->url);
        self::assertSame('RSS', $alerts[0]->source);
        self::assertStringContainsString('roman graphique', (string) $alerts[0]->excerpt); // balises retirées
        self::assertSame('2026-07-19T10:30:00+00:00', $alerts[0]->postedAt);
    }

    public function testEmptyFeedUrlYieldsNothing(): void
    {
        self::assertSame([], $this->fetch(new MockHttpClient(new MockResponse(self::FEED)), ''));
    }

    public function testNetworkFailureIsSwallowed(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('boom');
        });

        self::assertSame([], $this->fetch($client));
    }

    public function testGarbageFeedYieldsNothing(): void
    {
        self::assertSame([], $this->fetch(new MockHttpClient(new MockResponse('not-xml-at-all'))));
    }

    public function testRejectsNonHttpLinkAsUrl(): void
    {
        // Anti-XSS : un lien javascript: ne doit jamais devenir l'URL rendue en href.
        $feed = '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Piège</title><link>javascript:alert(1)</link></item></channel></rss>';
        $alerts = $this->fetch(new MockHttpClient(new MockResponse($feed)));

        self::assertCount(1, $alerts);
        self::assertNull($alerts[0]->url);
    }

    public function testTruncatesLongTitleToColumnBound(): void
    {
        $feed = '<?xml version="1.0"?><rss version="2.0"><channel><item><title>'.str_repeat('a', 400).'</title></item></channel></rss>';
        $alerts = $this->fetch(new MockHttpClient(new MockResponse($feed)));

        self::assertCount(1, $alerts);
        self::assertLessThanOrEqual(300, mb_strlen($alerts[0]->title));
    }
}
