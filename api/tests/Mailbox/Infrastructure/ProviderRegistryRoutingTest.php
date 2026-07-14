<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Infrastructure\Fetcher\FakeReplyFetcher;
use App\Mailbox\Infrastructure\Fetcher\GmailReplyFetcher;
use App\Mailbox\Infrastructure\Fetcher\OutlookReplyFetcher;
use App\Mailbox\Infrastructure\Fetcher\ProviderReplyFetcherRegistry;
use App\Mailbox\Infrastructure\OAuth\FakeMailboxConnector;
use App\Mailbox\Infrastructure\OAuth\GmailConnector;
use App\Mailbox\Infrastructure\OAuth\OutlookConnector;
use App\Mailbox\Infrastructure\OAuth\ProviderMailboxConnectorRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/** Le routage par fournisseur : réel si identifiants présents, factice sinon. */
final class ProviderRegistryRoutingTest extends TestCase
{
    public function testConnectorRoutesByProviderWhenCredentialsPresent(): void
    {
        $http = new MockHttpClient();
        $registry = new ProviderMailboxConnectorRegistry(
            new FakeMailboxConnector('http://localhost/cb'),
            new GmailConnector($http, 'g-id', 'g-secret', 'http://localhost/g'),
            new OutlookConnector($http, 'm-id', 'm-secret', 'http://localhost/m'),
            googleClientId: 'g-id',
            microsoftClientId: 'm-id',
        );

        self::assertInstanceOf(GmailConnector::class, $registry->connectorFor('GMAIL'));
        self::assertInstanceOf(OutlookConnector::class, $registry->connectorFor('OUTLOOK'));
    }

    public function testFallsBackToFakeWithoutCredentials(): void
    {
        $http = new MockHttpClient();
        $registry = new ProviderMailboxConnectorRegistry(
            new FakeMailboxConnector('http://localhost/cb'),
            new GmailConnector($http, '', '', 'http://localhost/g'),
            new OutlookConnector($http, '', '', 'http://localhost/m'),
            googleClientId: '',
            microsoftClientId: '',
        );

        self::assertInstanceOf(FakeMailboxConnector::class, $registry->connectorFor('GMAIL'));
        self::assertInstanceOf(FakeMailboxConnector::class, $registry->connectorFor('OUTLOOK'));
        self::assertInstanceOf(FakeMailboxConnector::class, $registry->connectorFor('UNKNOWN'));
    }

    public function testFetcherRegistryRoutesOutlookWhenMicrosoftConfigured(): void
    {
        $http = new MockHttpClient();
        $registry = new ProviderReplyFetcherRegistry(
            new FakeReplyFetcher(),
            new GmailReplyFetcher($http, '', ''),
            new OutlookReplyFetcher($http, 'm-id', 'm-secret'),
            googleClientId: '',
            microsoftClientId: 'm-id',
        );

        // Gmail sans identifiants → factice ; Outlook configuré → ACL Graph.
        self::assertInstanceOf(FakeReplyFetcher::class, $registry->fetcherFor('GMAIL'));
        self::assertInstanceOf(OutlookReplyFetcher::class, $registry->fetcherFor('OUTLOOK'));
    }
}
