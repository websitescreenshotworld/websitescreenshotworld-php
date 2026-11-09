<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebsiteScreenshotWorld\Client;
use WebsiteScreenshotWorld\Exception\AuthenticationException;
use WebsiteScreenshotWorld\Exception\CaptureException;
use WebsiteScreenshotWorld\Exception\InvalidRequestException;
use WebsiteScreenshotWorld\Exception\NotFoundException;
use WebsiteScreenshotWorld\Exception\PlanLimitException;
use WebsiteScreenshotWorld\Exception\RateLimitException;
use WebsiteScreenshotWorld\Exception\ServiceException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\InstantSnap\CaptureFile;
use WebsiteScreenshotWorld\RequestOptions;
use WebsiteScreenshotWorld\Tests\Support\ConformanceServer;
use WebsiteScreenshotWorld\Tests\Support\Json;
use WebsiteScreenshotWorld\Tests\Support\WireOptions;

/**
 * Plays every fixture in spec/fixtures/ against the library, as spec/README.md "Fixtures" describes.
 */
final class ConformanceTest extends TestCase
{
    private const EXCEPTIONS = [
        'base' => WebsiteScreenshotWorldException::class,
        'authentication' => AuthenticationException::class,
        'invalid_request' => InvalidRequestException::class,
        'plan_limit' => PlanLimitException::class,
        'not_found' => NotFoundException::class,
        'rate_limit' => RateLimitException::class,
        'capture' => CaptureException::class,
        'service' => ServiceException::class,
    ];

    private static ?ConformanceServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$server = new ConformanceServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function fixtures(): iterable
    {
        foreach (Json::specFiles('fixtures') as $path) {
            if (basename($path) !== 'keys.json') {
                yield basename($path, '.json') => [Json::file($path)];
            }
        }
    }

    /**
     * @param array<string, mixed> $fixture
     */
    #[DataProvider('fixtures')]
    public function testFixture(array $fixture): void
    {
        $server = self::$server ?? throw new \LogicException('The server is not running.');
        $server->load(Json::list($fixture['responses']));

        $keys = Json::file(Json::SPEC_DIR . '/fixtures/keys.json');
        $client = new Client(
            instantSnapApiKey: Json::string($keys['instant_snap']),
            autoScheduleApiKey: Json::string($keys['auto_schedule']),
            instantSnapBaseUrl: $server->baseUrl,
            autoScheduleBaseUrl: $server->baseUrl,
        );

        $result = null;
        $error = null;
        try {
            $result = self::call($client, Json::object($fixture['call']));
        } catch (WebsiteScreenshotWorldException $e) {
            $error = $e;
        }

        self::assertRequests(Json::list($fixture['expect_requests']), $server->requests());

        $expect = Json::object($fixture['expect']);
        if (isset($expect['error'])) {
            self::assertNull($result, 'The call should have failed.');
            self::assertError(Json::object($expect['error']), $error);
        } else {
            self::assertNull($error, 'The call failed: ' . $error?->getMessage());
            self::assertIsObject($result);
            self::assertResult(Json::object($expect['result']), $result);
        }
    }

    /**
     * @param array<string, mixed> $call
     */
    private static function call(Client $client, array $call): object
    {
        $requestOptions = new RequestOptions(
            idempotencyKey: isset($call['idempotency_key']) ? Json::string($call['idempotency_key']) : null,
        );
        $options = static fn() => WireOptions::captureOptions(Json::object($call['options']));

        return match (Json::string($call['product']) . '.' . Json::string($call['method'])) {
            'instant_snap.capture' => $client->instantSnap->capture($options(), $requestOptions),
            'instant_snap.capture_file' => $client->instantSnap->captureFile($options(), $requestOptions),
            'instant_snap.capture_to_bucket' => $client->instantSnap->captureToBucket($options(), $requestOptions),
            'instant_snap.submit' => $client->instantSnap->submit($options(), $requestOptions),
            'instant_snap.submit_to_bucket' => $client->instantSnap->submitToBucket($options(), $requestOptions),
            'instant_snap.wait' => $client->instantSnap->wait(
                Json::string($call['id']),
                isset($call['timeout_seconds']) ? self::seconds($call['timeout_seconds']) : 360.0,
                isset($call['poll_interval_seconds']) ? self::seconds($call['poll_interval_seconds']) : 3.0,
                $requestOptions,
            ),
            'instant_snap.get' => $client->instantSnap->get(Json::string($call['id']), $requestOptions),
            'instant_snap.defaults' => $client->instantSnap->defaults($requestOptions),
            'instant_snap.catalog' => $client->instantSnap->catalog($requestOptions),
            'auto_schedule.trigger_now' => $client->autoSchedule->triggerNow(Json::string($call['web_page_id']), $requestOptions),
            default => throw new \LogicException('Unknown fixture call.'),
        };
    }

    private static function seconds(mixed $value): float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : throw new \LogicException('Not a number of seconds.');
    }

    /**
     * @param list<mixed> $expected
     * @param list<array<string, mixed>> $actual
     */
    private static function assertRequests(array $expected, array $actual): void
    {
        self::assertCount(\count($expected), $actual, 'Wrong number of requests.');

        $idempotencyKeys = [];
        foreach ($expected as $index => $item) {
            $want = Json::object($item);
            $got = $actual[$index];
            $headers = array_change_key_case(Json::object($got['headers']));

            self::assertSame($want['method'], $got['method']);
            self::assertSame($want['path'], $got['path']);
            foreach (Json::object($want['headers'] ?? []) as $name => $value) {
                self::assertSame($value, $headers[strtolower($name)] ?? null, "Header {$name}.");
            }
            if (isset($want['idempotency_key'])) {
                $key = $headers['idempotency-key'] ?? null;
                self::assertIsString($key);
                self::assertNotSame('', $key);
                $idempotencyKeys[] = $key;
            }
            if (\array_key_exists('body', $want)) {
                $body = $got['body'];
                self::assertSame(
                    Json::canonical($want['body']),
                    $body === null ? null : Json::canonical(json_decode(Json::string($body), true, 512, \JSON_THROW_ON_ERROR)),
                );
            }
        }

        self::assertLessThanOrEqual(1, \count(array_unique($idempotencyKeys)), 'The Idempotency-Key changed between retries.');
    }

    /**
     * @param array<string, mixed> $expected
     */
    private static function assertError(array $expected, ?WebsiteScreenshotWorldException $error): void
    {
        self::assertNotNull($error);
        self::assertSame(self::EXCEPTIONS[Json::string($expected['kind'])], $error::class);
        self::assertSame($expected['error_code'], $error->getErrorCode());
        self::assertSame($expected['http_status'], $error->getHttpStatus());
        if (\array_key_exists('message', $expected)) {
            self::assertSame($expected['message'], $error->getMessage());
        }
        if (\array_key_exists('retry_after_seconds', $expected)) {
            self::assertSame($expected['retry_after_seconds'], $error->getRetryAfterSeconds());
        }
    }

    /**
     * Compares the listed wire fields with the result's camelCase properties.
     *
     * @param array<string, mixed> $expected
     */
    private static function assertResult(array $expected, object $actual): void
    {
        foreach ($expected as $field => $value) {
            if ($field === 'body_sha256') {
                self::assertInstanceOf(CaptureFile::class, $actual);
                self::assertSame($value, hash('sha256', $actual->body->getContents()));

                continue;
            }

            $property = lcfirst(str_replace('_', '', ucwords($field, '_')));
            self::assertTrue(property_exists($actual, $property), "No property {$property}.");
            $got = $actual->{$property};

            if (\is_array($value) && !array_is_list($value)) {
                self::assertIsObject($got, "{$property} should be an object.");
                self::assertResult(Json::object($value), $got);
            } else {
                self::assertSame($value, $got, "Field {$field}.");
            }
        }
    }
}
