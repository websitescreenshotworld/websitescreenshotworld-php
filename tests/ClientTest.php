<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use WebsiteScreenshotWorld\Client;
use WebsiteScreenshotWorld\Exception\AuthenticationException;
use WebsiteScreenshotWorld\Exception\ConnectionException;
use WebsiteScreenshotWorld\Exception\NotFoundException;
use WebsiteScreenshotWorld\Exception\RateLimitException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\InstantSnap\Capture;
use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;
use WebsiteScreenshotWorld\InstantSnap\CaptureStatus;
use WebsiteScreenshotWorld\InstantSnap\S3DeliveryOptions;
use WebsiteScreenshotWorld\InstantSnap\TimestampWatermark;
use WebsiteScreenshotWorld\RequestOptions;
use WebsiteScreenshotWorld\Tests\Support\FakeHttpClient;
use WebsiteScreenshotWorld\Tests\Support\NetworkFailure;

final class ClientTest extends TestCase
{
    private const CAPTURE = [
        'success' => true,
        'id' => '3f6c2a1e-9b8d-4c7e-a5f4-1d2e3c4b5a69',
        'status' => 'succeeded',
        'response_type' => 'json',
        'media_category' => 'image',
        'format' => 'png',
        'content_type' => 'image/png',
        'size_bytes' => 5,
        'width' => 1920,
        'height' => 1080,
        'duration_ms' => 4210,
        'page_http_status' => 200,
        'captured_at' => '2026-09-26T10:30:04.512Z',
        'final_url' => null,
        'media_sha256' => null,
        'download_url' => 'https://media.example.com/s/0.png?e=1&s=abc',
        'expires_at' => '2026-09-27T10:30:04.512+05:30',
        'page_source' => null,
        's3_delivery' => null,
        'callback_url' => null,
        'error_code' => null,
        'spec_version' => 'v1',
        'added_later' => 'ignored',
    ];

    private string $directory;

    protected function setUp(): void
    {
        foreach (['WSW_INSTANT_SNAP_API_KEY', 'WSW_AUTO_SCHEDULE_API_KEY'] as $variable) {
            putenv($variable);
            unset($_ENV[$variable], $_SERVER[$variable]);
        }
        $this->directory = sys_get_temp_dir() . '/wsw-php-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->directory . '/*') ?: []);
        rmdir($this->directory);
        putenv('WSW_INSTANT_SNAP_API_KEY');
    }

    public function testMissingKeyFailsBeforeAnythingIsSent(): void
    {
        $http = new FakeHttpClient();
        $client = self::client($http, apiKey: null);

        try {
            $client->instantSnap->capture(new CaptureOptions(url: 'https://example.com'));
            self::fail('The capture should have failed.');
        } catch (AuthenticationException $e) {
            self::assertStringContainsString('WSW_INSTANT_SNAP_API_KEY', $e->getMessage());
            self::assertNull($e->getErrorCode());
        }
        self::assertSame([], $http->requests);
    }

    public function testMissingAutoScheduleKeyFailsBeforeAnythingIsSent(): void
    {
        $http = new FakeHttpClient();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('WSW_AUTO_SCHEDULE_API_KEY');

        try {
            self::client($http)->autoSchedule->triggerNow('7d4e2b1a-6c5f-4a3e-9d8c-2b1a0f9e8d7c');
        } finally {
            self::assertSame([], $http->requests);
        }
    }

    public function testKeyDefaultsToTheEnvironment(): void
    {
        putenv('WSW_INSTANT_SNAP_API_KEY=wsw_api_from_env');
        $http = new FakeHttpClient(self::json(['success' => true, 'emulated_devices' => [], 'proxy_countries' => []]));

        self::client($http, apiKey: null)->instantSnap->catalog();

        self::assertSame('wsw_api_from_env', $http->requests[0]->getHeaderLine('X-Api-Key'));
    }

    public function testDefaultsNeedNoKey(): void
    {
        $http = new FakeHttpClient(self::json(['success' => true, 'spec_version' => 'v1', 'viewport' => ['width' => 1920]]));

        $defaults = self::client($http, apiKey: null)->instantSnap->defaults();

        self::assertSame('v1', $defaults->specVersion);
        self::assertSame(1920, $defaults->viewport?->width);
        self::assertFalse($http->requests[0]->hasHeader('X-Api-Key'));
    }

    public function testEveryRequestCarriesTheLibraryHeaders(): void
    {
        $http = new FakeHttpClient(self::json(self::CAPTURE));

        self::client($http)->instantSnap->get('3f6c2a1e-9b8d-4c7e-a5f4-1d2e3c4b5a69');

        $request = $http->requests[0];
        self::assertMatchesRegularExpression(
            '#^websitescreenshotworld-php/0\.1\.0 \(PHP \d+\.\d+\.\d+\)$#',
            $request->getHeaderLine('User-Agent'),
        );
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('wsw_api_test', $request->getHeaderLine('X-Api-Key'));
        self::assertFalse($request->hasHeader('Content-Type'));
    }

    public function testEachCaptureGetsItsOwnIdempotencyKeyUnlessOneIsGiven(): void
    {
        $http = new FakeHttpClient(self::json(self::CAPTURE), self::json(self::CAPTURE), self::json(self::CAPTURE));
        $client = self::client($http);
        $options = new CaptureOptions(url: 'https://example.com');

        $client->instantSnap->capture($options);
        $client->instantSnap->capture($options);
        $client->instantSnap->capture($options, new RequestOptions(idempotencyKey: 'order-1'));

        $keys = array_map(static fn($request): string => $request->getHeaderLine('Idempotency-Key'), $http->requests);
        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
        self::assertMatchesRegularExpression($uuid, $keys[0]);
        self::assertMatchesRegularExpression($uuid, $keys[1]);
        self::assertNotSame($keys[0], $keys[1]);
        self::assertSame('order-1', $keys[2]);
    }

    public function testCaptureParsesDatesAndIgnoresUnknownFields(): void
    {
        $capture = self::client(new FakeHttpClient(self::json(self::CAPTURE)))
            ->instantSnap->capture(new CaptureOptions(url: 'https://example.com'));

        self::assertSame('2026-09-26T10:30:04.512+00:00', $capture->capturedAt?->format('Y-m-d\TH:i:s.vP'));
        self::assertSame('2026-09-27T05:00:04.512+00:00', $capture->expiresAt?->format('Y-m-d\TH:i:s.vP'));
        self::assertArrayNotHasKey('success', $capture->raw);
        self::assertSame('ignored', $capture->raw['added_later']);
    }

    public function testEmptyBlocksAreSentAsJsonObjects(): void
    {
        $http = new FakeHttpClient(self::json(self::CAPTURE));

        self::client($http)->instantSnap->capture(new CaptureOptions(
            url: 'https://example.com',
            autoHeight: false,
            zoom: 0,
            userAgent: '',
            headers: [],
            timestampWatermark: new TimestampWatermark(),
        ));

        self::assertSame(
            '{"url":"https://example.com","auto_height":false,"zoom":0,"user_agent":"","headers":{},"timestamp_watermark":{},"response_type":"json"}',
            (string) $http->requests[0]->getBody(),
        );
    }

    public function testCaptureFileExposesTheStreamAndSavesIt(): void
    {
        $http = new FakeHttpClient(new Response(200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"fallback.pdf\"; filename*=UTF-8''r%C3%A9sum%C3%A9.pdf",
            'X-InstantSnap-Id' => 'abc',
            'X-InstantSnap-Status' => 'delivered',
            'X-InstantSnap-Part-Count' => '2',
            'X-InstantSnap-Captured-At' => '2026-09-26T10:30:04Z',
        ], '%PDF-'));

        $file = self::client($http)->instantSnap->captureFile(new CaptureOptions(url: 'https://example.com'));
        $path = $this->directory . '/capture.pdf';

        self::assertSame('résumé.pdf', $file->filename);
        self::assertSame(2, $file->partCount);
        self::assertNull($file->width);
        self::assertSame('2026-09-26 10:30:04', $file->capturedAt?->format('Y-m-d H:i:s'));
        self::assertSame(5, $file->saveTo($path));
        self::assertSame('%PDF-', file_get_contents($path));
        self::assertFalse($http->requests[0]->hasHeader('Accept'));
    }

    public function testDownloadWritesToAPathWithoutSendingTheKey(): void
    {
        $http = new FakeHttpClient(new Response(200, ['Content-Type' => 'image/png'], 'bytes'));
        $path = $this->directory . '/capture.png';

        $written = self::client($http)->instantSnap->download(new Capture(self::CAPTURE), $path);

        self::assertSame(5, $written);
        self::assertSame('bytes', file_get_contents($path));
        $request = $http->requests[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame(self::CAPTURE['download_url'], (string) $request->getUri());
        self::assertFalse($request->hasHeader('X-Api-Key'));
        self::assertFalse($request->hasHeader('Accept'));
    }

    public function testDownloadWritesToStreams(): void
    {
        $http = new FakeHttpClient(new Response(200, [], 'first'), new Response(200, [], 'second'));
        $client = self::client($http);
        $resource = fopen('php://memory', 'w+b');
        self::assertIsResource($resource);
        $stream = (new HttpFactory())->createStream();

        $client->instantSnap->download(new Capture(self::CAPTURE), $resource);
        $client->instantSnap->download(new Capture(self::CAPTURE), $stream);

        rewind($resource);
        self::assertSame('first', stream_get_contents($resource));
        self::assertSame('second', (string) $stream);
    }

    public function testDownloadNeedsADownloadUrl(): void
    {
        $http = new FakeHttpClient();

        $this->expectException(\InvalidArgumentException::class);

        self::client($http)->instantSnap->download(new Capture(['download_url' => null] + self::CAPTURE), $this->directory . '/x');
    }

    public function testConnectionFailuresAreRetriedThenReported(): void
    {
        $http = new FakeHttpClient(new NetworkFailure('reset'), new NetworkFailure('reset'));

        try {
            self::client($http, maxRetries: 1)->instantSnap->capture(new CaptureOptions(url: 'https://example.com'));
            self::fail('The capture should have failed.');
        } catch (ConnectionException $e) {
            self::assertInstanceOf(NetworkFailure::class, $e->getPrevious());
            self::assertNull($e->getHttpStatus());
        }
        self::assertCount(2, $http->requests);
        self::assertSame(
            $http->requests[0]->getHeaderLine('Idempotency-Key'),
            $http->requests[1]->getHeaderLine('Idempotency-Key'),
        );
        self::assertSame((string) $http->requests[0]->getBody(), (string) $http->requests[1]->getBody());
    }

    public function testRetryAfterHeaderIsUsedWhenTheBodyHasNone(): void
    {
        $http = new FakeHttpClient(new Response(429, ['Retry-After' => '0'], 'Too Many Requests'));

        try {
            self::client($http, maxRetries: 0)->instantSnap->get('x');
            self::fail('The call should have failed.');
        } catch (WebsiteScreenshotWorldException $e) {
            self::assertSame('rate_limited', $e->getErrorCode());
            self::assertSame(0, $e->getRetryAfterSeconds());
            self::assertSame('Too Many Requests', $e->getRawBody());
        }
    }

    public function testSuccessWithAnUnreadableBodyFails(): void
    {
        $this->expectException(WebsiteScreenshotWorldException::class);
        $this->expectExceptionMessage('not a JSON object');

        self::client(new FakeHttpClient(new Response(200, [], '<html>')))->instantSnap->get('x');
    }

    public function testSubmitSendsAsyncAndReturnsTheQueuedCapture(): void
    {
        $queued = ['status' => 'queued', 'media_category' => null, 'format' => null, 'download_url' => null] + self::CAPTURE;
        $http = new FakeHttpClient(new Response(202, ['Location' => '/instant-snap/v1/captures/x'], json_encode($queued, \JSON_THROW_ON_ERROR)));

        $capture = self::client($http)->instantSnap->submit(new CaptureOptions(url: 'https://example.com', timeout: 240));

        self::assertSame(CaptureStatus::QUEUED, $capture->status);
        self::assertNull($capture->mediaCategory);
        $body = json_decode((string) $http->requests[0]->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['url' => 'https://example.com', 'timeout' => 240, 'response_type' => 'json', 'async' => true], $body);
        self::assertNotSame('', $http->requests[0]->getHeaderLine('Idempotency-Key'));
    }

    public function testQueueFullIsARateLimitAndIsNotRetried(): void
    {
        $refusal = ['success' => false, 'error_code' => 'queue_full', 'error_message' => 'Queue full.', 'retry_after_seconds' => 30];
        $http = new FakeHttpClient(new Response(429, [], json_encode($refusal, \JSON_THROW_ON_ERROR)));

        try {
            self::client($http)->instantSnap->submit(new CaptureOptions(url: 'https://example.com'));
            self::fail('The submit should have failed.');
        } catch (RateLimitException $e) {
            self::assertSame('queue_full', $e->getErrorCode());
            self::assertSame(30, $e->getRetryAfterSeconds());
        }
        self::assertCount(1, $http->requests);
    }

    public function testWaitReturnsTheLastCaptureWhenTimeRunsOut(): void
    {
        $running = self::json(['status' => 'running'] + self::CAPTURE);
        $http = new FakeHttpClient($running);

        $capture = self::client($http)->instantSnap->wait('3f6c2a1e-9b8d-4c7e-a5f4-1d2e3c4b5a69', timeoutSeconds: 0, pollIntervalSeconds: 0);

        self::assertSame(CaptureStatus::RUNNING, $capture->status);
        self::assertCount(1, $http->requests);
    }

    public function testWaitAcceptsACaptureAndStopsAtAFinishedStatus(): void
    {
        $http = new FakeHttpClient(
            self::json(['status' => 'pending'] + self::CAPTURE),
            self::json(['status' => 'failed', 'error_code' => 'capture_expired'] + self::CAPTURE),
        );
        $queued = new Capture(['status' => 'queued'] + self::CAPTURE);

        $capture = self::client($http)->instantSnap->wait($queued, pollIntervalSeconds: 0);

        self::assertSame(CaptureStatus::FAILED, $capture->status);
        self::assertSame('capture_expired', $capture->errorCode);
        self::assertCount(2, $http->requests);
        self::assertStringEndsWith('/instant-snap/v1/captures/' . $queued->id, (string) $http->requests[1]->getUri());
    }

    public function testWaitPassesRefusalsFromGetThrough(): void
    {
        $refusal = ['success' => false, 'error_code' => 'capture_not_found', 'error_message' => 'No such capture.', 'retry_after_seconds' => null];
        $http = new FakeHttpClient(new Response(404, [], json_encode($refusal, \JSON_THROW_ON_ERROR)));

        $this->expectException(NotFoundException::class);

        self::client($http)->instantSnap->wait('x', pollIntervalSeconds: 0);
    }

    public function testBucketSecretIsHiddenFromDumps(): void
    {
        $options = new S3DeliveryOptions(accessKeyId: 'AKIA', secretAccessKey: 'very-secret', bucket: 'b');

        self::assertStringNotContainsString('very-secret', print_r($options, true));
        self::assertSame('very-secret', $options->jsonSerialize()->secret_access_key);
    }

    private static function client(FakeHttpClient $http, ?string $apiKey = 'wsw_api_test', int $maxRetries = 2): Client
    {
        return new Client(instantSnapApiKey: $apiKey, maxRetries: $maxRetries, httpClient: $http);
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function json(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body, \JSON_THROW_ON_ERROR));
    }
}
