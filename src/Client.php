<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WebsiteScreenshotWorld\AutoSchedule\AutoScheduleService;
use WebsiteScreenshotWorld\InstantSnap\InstantSnapService;
use WebsiteScreenshotWorld\Internal\HttpClients;
use WebsiteScreenshotWorld\Internal\HttpTransport;

/**
 * The entry point for both APIs.
 *
 * ```php
 * $client = new Client(instantSnapApiKey: '...', autoScheduleApiKey: '...');
 * $capture = $client->instantSnap->capture(new CaptureOptions(url: 'https://example.com'));
 * ```
 *
 * Either key may be left out; calling the product whose key is missing throws before anything is sent.
 */
final class Client
{
    public const INSTANT_SNAP_BASE_URL = 'https://api.websitescreenshotworld.com';

    public const AUTO_SCHEDULE_BASE_URL = 'https://autoapi.websitescreenshotworld.com';

    public readonly InstantSnapService $instantSnap;

    public readonly AutoScheduleService $autoSchedule;

    /**
     * @param string|null $instantSnapApiKey Defaults to the WSW_INSTANT_SNAP_API_KEY environment variable.
     * @param string|null $autoScheduleApiKey Defaults to the WSW_AUTO_SCHEDULE_API_KEY environment variable.
     * @param float $captureTimeoutSeconds How long a capture call may take, retries included. The API can work for
     *                                     about 120 seconds, so keep this above that.
     * @param float $timeoutSeconds How long any other call may take, retries included.
     * @param int $maxRetries How many times a retryable failure is retried.
     * @param ClientInterface|null $httpClient A PSR-18 client. When left out one is discovered, and if it is
     *                                         Guzzle or Symfony HttpClient the timeouts above are applied to it and
     *                                         redirects are turned off. A client you pass in keeps its own timeouts
     *                                         and must not follow redirects.
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $instantSnapApiKey = null,
        #[\SensitiveParameter]
        ?string $autoScheduleApiKey = null,
        string $instantSnapBaseUrl = self::INSTANT_SNAP_BASE_URL,
        string $autoScheduleBaseUrl = self::AUTO_SCHEDULE_BASE_URL,
        float $captureTimeoutSeconds = 180.0,
        float $timeoutSeconds = 30.0,
        int $maxRetries = 2,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $transport = new HttpTransport(
            new HttpClients($httpClient),
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            max(0, $maxRetries),
            static function (float $seconds): void {
                if ($seconds > 0) {
                    usleep((int) round($seconds * 1_000_000));
                }
            },
        );

        $this->instantSnap = new InstantSnapService(
            $transport,
            self::key($instantSnapApiKey, 'WSW_INSTANT_SNAP_API_KEY'),
            rtrim($instantSnapBaseUrl, '/'),
            $captureTimeoutSeconds,
            $timeoutSeconds,
        );
        $this->autoSchedule = new AutoScheduleService(
            $transport,
            self::key($autoScheduleApiKey, 'WSW_AUTO_SCHEDULE_API_KEY'),
            rtrim($autoScheduleBaseUrl, '/'),
            $timeoutSeconds,
        );
    }

    private static function key(#[\SensitiveParameter] ?string $given, string $variable): ?string
    {
        $value = $given ?? $_ENV[$variable] ?? $_SERVER[$variable] ?? getenv($variable);

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
