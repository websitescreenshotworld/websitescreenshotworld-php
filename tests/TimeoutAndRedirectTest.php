<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use WebsiteScreenshotWorld\Client;
use WebsiteScreenshotWorld\Exception\ConnectionException;
use WebsiteScreenshotWorld\Exception\RateLimitException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Tests\Support\FakeHttpClient;
use WebsiteScreenshotWorld\Tests\Support\NetworkFailure;

final class TimeoutAndRedirectTest extends TestCase
{
    public function testRetryWaitBeyondTheTimeoutIsNotTaken(): void
    {
        $http = new FakeHttpClient(
            self::refusal(429, 'rate_limited', 30),
            self::refusal(429, 'rate_limited', 30),
        );
        $start = hrtime(true);

        try {
            self::client($http, timeoutSeconds: 2.0)->instantSnap->get('x');
            self::fail('The call should have failed.');
        } catch (RateLimitException $e) {
            self::assertSame(30, $e->getRetryAfterSeconds());
        }

        self::assertCount(1, $http->requests);
        self::assertLessThan(1.0, (hrtime(true) - $start) / 1e9);
    }

    public function testTimeoutCoversRetries(): void
    {
        // Backoff waits about 0.5 s, then 1 s: the second wait would run past 1.2 s, so it is not taken.
        $http = new FakeHttpClient(...array_fill(0, 10, self::refusal(503, 'service_unavailable', null)));
        $start = hrtime(true);

        try {
            self::client($http, timeoutSeconds: 1.2, maxRetries: 1000)->instantSnap->get('x');
            self::fail('The call should have failed.');
        } catch (WebsiteScreenshotWorldException $e) {
            self::assertSame('service_unavailable', $e->getErrorCode());
        }

        self::assertLessThan(1.2, (hrtime(true) - $start) / 1e9);
        self::assertCount(2, $http->requests);
    }

    public function testCallThatRunsOutOfTimeIsNotRetried(): void
    {
        $http = new class implements ClientInterface {
            public int $calls = 0;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ++$this->calls;
                usleep(200_000);

                throw new NetworkFailure('Operation timed out');
            }
        };

        try {
            (new Client(instantSnapApiKey: 'wsw_api_test', timeoutSeconds: 0.1, maxRetries: 2, httpClient: $http))
                ->instantSnap->get('x');
            self::fail('The call should have failed.');
        } catch (ConnectionException $e) {
            self::assertStringContainsString('No answer from', $e->getMessage());
            self::assertInstanceOf(NetworkFailure::class, $e->getPrevious());
        }

        self::assertSame(1, $http->calls);
    }

    public function testDiscoveredClientDoesNotFollowRedirects(): void
    {
        $directory = sys_get_temp_dir() . '/wsw-redirect-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $marker = $directory . '/followed';
        file_put_contents($directory . '/router.php', <<<PHP
            <?php
            if (\$_SERVER['REQUEST_URI'] === '/elsewhere') {
                file_put_contents('{$marker}', \$_SERVER['HTTP_X_API_KEY'] ?? '');
                header('Content-Type: application/json');
                echo '{}';
                return true;
            }
            header('Location: /elsewhere', true, 302);
            return true;
            PHP);

        $port = self::freePort();
        $server = proc_open(
            [\PHP_BINARY, '-S', "127.0.0.1:{$port}", $directory . '/router.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        self::assertNotFalse($server);

        try {
            self::waitForPort($port);
            $client = new Client(instantSnapApiKey: 'wsw_api_secret', instantSnapBaseUrl: "http://127.0.0.1:{$port}");

            try {
                $client->instantSnap->get('x');
                self::fail('The call should have failed.');
            } catch (WebsiteScreenshotWorldException $e) {
                self::assertSame(302, $e->getHttpStatus());
            }

            self::assertFileDoesNotExist($marker);
        } finally {
            proc_terminate($server);
            proc_close($server);
            @unlink($marker);
            @unlink($directory . '/router.php');
            @rmdir($directory);
        }
    }

    private static function client(FakeHttpClient $http, float $timeoutSeconds = 30.0, int $maxRetries = 2): Client
    {
        return new Client(instantSnapApiKey: 'wsw_api_test', timeoutSeconds: $timeoutSeconds, maxRetries: $maxRetries, httpClient: $http);
    }

    private static function refusal(int $status, string $code, ?int $retryAfter): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode([
            'success' => false,
            'error_code' => $code,
            'error_message' => 'Try again.',
            'retry_after_seconds' => $retryAfter,
        ], \JSON_THROW_ON_ERROR));
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }

    private static function waitForPort(int $port): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($connection !== false) {
                fclose($connection);

                return;
            }
            usleep(50_000);
        }
        self::fail('The test server did not start.');
    }
}
