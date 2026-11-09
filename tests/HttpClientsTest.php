<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests;

use GuzzleHttp\Client as GuzzleClient;
use PHPUnit\Framework\TestCase;
use WebsiteScreenshotWorld\Internal\HttpClients;
use WebsiteScreenshotWorld\Tests\Support\FakeHttpClient;

final class HttpClientsTest extends TestCase
{
    public function testDiscoveredGuzzleGetsTheTimeout(): void
    {
        $clients = new HttpClients(null);

        $capture = $clients->forTimeout(180.0);
        $other = $clients->forTimeout(30.0);

        self::assertInstanceOf(GuzzleClient::class, $capture);
        self::assertInstanceOf(GuzzleClient::class, $other);
        self::assertSame(180.0, $capture->getConfig('timeout'));
        self::assertSame(30.0, $other->getConfig('timeout'));
        self::assertSame($capture, $clients->forTimeout(180.0));
        self::assertFalse($capture->getConfig('allow_redirects'));
    }

    public function testRetriesGetAClientForTheTimeLeft(): void
    {
        $clients = new HttpClients(null);

        $retry = $clients->forRemaining(12.5);

        self::assertInstanceOf(GuzzleClient::class, $retry);
        self::assertSame(12.5, $retry->getConfig('timeout'));
        self::assertFalse($retry->getConfig('allow_redirects'));
    }

    public function testGivenClientIsUsedAsItIs(): void
    {
        $given = new FakeHttpClient();
        $clients = new HttpClients($given);

        self::assertSame($given, $clients->forTimeout(180.0));
        self::assertSame($given, $clients->forTimeout(30.0));
        self::assertSame($given, $clients->forRemaining(5.0));
    }
}
