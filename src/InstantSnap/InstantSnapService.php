<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use WebsiteScreenshotWorld\Exception\AuthenticationException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Internal\HttpTransport;
use WebsiteScreenshotWorld\Internal\Streams;
use WebsiteScreenshotWorld\RequestOptions;

/**
 * On-demand captures. Reach it through `$client->instantSnap`.
 *
 * Every capture method sends an Idempotency-Key (yours from {@see RequestOptions}, or a random one) and reuses
 * it on retries, so a retry never captures twice.
 */
final class InstantSnapService
{
    /** The statuses {@see self::wait()} keeps reading through. */
    private const UNFINISHED = [CaptureStatus::QUEUED, CaptureStatus::RUNNING, CaptureStatus::PENDING];

    /**
     * @internal Use {@see \WebsiteScreenshotWorld\Client}.
     */
    public function __construct(
        private readonly HttpTransport $transport,
        #[\SensitiveParameter]
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly float $captureTimeoutSeconds,
        private readonly float $timeoutSeconds,
    ) {}

    /**
     * Captures a page and answers with its metadata and a download link valid for 60 minutes.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function capture(CaptureOptions $options, ?RequestOptions $requestOptions = null): Capture
    {
        return new Capture(HttpTransport::decode($this->sendCapture($options, ResponseType::JSON, $requestOptions)));
    }

    /**
     * Captures a page and answers with the file itself. The API does not keep it.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function captureFile(CaptureOptions $options, ?RequestOptions $requestOptions = null): CaptureFile
    {
        return new CaptureFile($this->sendCapture($options, ResponseType::BY_FORMAT, $requestOptions, acceptJson: false));
    }

    /**
     * Captures a page and uploads it to the bucket in `$options->s3Delivery`.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function captureToBucket(CaptureOptions $options, ?RequestOptions $requestOptions = null): Capture
    {
        return new Capture(HttpTransport::decode($this->sendCapture($options, ResponseType::CLOUD_STORAGE, $requestOptions)));
    }

    /**
     * Queues a capture and answers at once with it, `status` {@see CaptureStatus::QUEUED}. The capture runs when a
     * browser is free; read it with {@see self::wait()} or {@see self::get()}, or have `callbackUrl` notified.
     * A capture still queued 15 minutes after it was accepted ends `failed` with `capture_expired`, not charged.
     *
     * The request uses the ordinary timeout (`timeoutSeconds`), not the capture timeout, since nothing runs while
     * it waits. A `queue_full` refusal is thrown as a {@see \WebsiteScreenshotWorld\Exception\RateLimitException}
     * carrying `getRetryAfterSeconds()`, and is not retried.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function submit(CaptureOptions $options, ?RequestOptions $requestOptions = null): Capture
    {
        return new Capture(HttpTransport::decode($this->sendCapture($options, ResponseType::JSON, $requestOptions, async: true)));
    }

    /**
     * Like {@see self::submit()}, uploading the file to the bucket in `$options->s3Delivery` once it is captured.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function submitToBucket(CaptureOptions $options, ?RequestOptions $requestOptions = null): Capture
    {
        return new Capture(HttpTransport::decode($this->sendCapture($options, ResponseType::CLOUD_STORAGE, $requestOptions, async: true)));
    }

    /**
     * Reads a capture with {@see self::get()} every `$pollIntervalSeconds` until its status is no longer
     * `queued`, `running` or `pending`, and returns it. When `$timeoutSeconds` runs out first, it returns the
     * capture as last read, still unfinished, rather than throwing, so you can decide whether to wait again.
     * A refusal from `get()` is thrown as it is.
     *
     * @param Capture|string $capture The capture, or its id.
     * @param float $timeoutSeconds How long to keep reading, 360 seconds by default.
     * @param float $pollIntervalSeconds The pause between reads, 3 seconds by default; 0 does not pause.
     * @param RequestOptions|null $requestOptions Applied to each `get()`.
     *
     * @throws WebsiteScreenshotWorldException
     * @throws \InvalidArgumentException When a duration is negative.
     */
    public function wait(
        Capture|string $capture,
        float $timeoutSeconds = 360.0,
        float $pollIntervalSeconds = 3.0,
        ?RequestOptions $requestOptions = null,
    ): Capture {
        if ($timeoutSeconds < 0 || $pollIntervalSeconds < 0) {
            throw new \InvalidArgumentException('timeoutSeconds and pollIntervalSeconds cannot be negative.');
        }
        $id = $capture instanceof Capture ? $capture->id : $capture;
        $deadline = self::now() + $timeoutSeconds;

        while (true) {
            $current = $this->get($id, $requestOptions);
            if (!\in_array($current->status, self::UNFINISHED, true)) {
                return $current;
            }
            $remaining = $deadline - self::now();
            if ($remaining <= 0) {
                return $current;
            }
            if ($pollIntervalSeconds > 0) {
                $this->transport->sleep(min($pollIntervalSeconds, $remaining));
            }
        }
    }

    /**
     * Reads a capture back, with a fresh download link.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function get(string $id, ?RequestOptions $requestOptions = null): Capture
    {
        $response = $this->transport->send(
            'GET',
            $this->baseUrl . '/instant-snap/v1/captures/' . rawurlencode($id),
            $requestOptions->timeoutSeconds ?? $this->timeoutSeconds,
            ['X-Api-Key' => $this->apiKey()],
        );

        return new Capture(HttpTransport::decode($response));
    }

    /**
     * Downloads a capture's file from its `downloadUrl`.
     *
     * @param string|resource|StreamInterface $target A file path (replaced if it exists), or a writable stream.
     *
     * @return int The number of bytes written.
     *
     * @throws WebsiteScreenshotWorldException
     * @throws \InvalidArgumentException When the capture has no download URL or the target is not a path or stream.
     * @throws \RuntimeException When the file cannot be written.
     */
    public function download(Capture $capture, mixed $target, ?RequestOptions $requestOptions = null): int
    {
        if ($capture->downloadUrl === null) {
            throw new \InvalidArgumentException(\sprintf(
                'Capture %s has no download URL. Files returned by captureFile() or uploaded to a bucket are not kept for download.',
                $capture->id,
            ));
        }
        Streams::assertTarget($target);

        // The link is signed, so the API key is not sent to the file host.
        $response = $this->transport->send(
            'GET',
            $capture->downloadUrl,
            $requestOptions->timeoutSeconds ?? $this->timeoutSeconds,
            acceptJson: false,
        );

        return Streams::copy($response->getBody(), $target);
    }

    /**
     * The values a capture takes for options it does not send, and the limits. Needs no API key.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function defaults(?RequestOptions $requestOptions = null): Defaults
    {
        $response = $this->transport->send(
            'GET',
            $this->baseUrl . '/instant-snap/v1/defaults',
            $requestOptions->timeoutSeconds ?? $this->timeoutSeconds,
        );

        return new Defaults(HttpTransport::decode($response));
    }

    /**
     * The devices `emulatedDevice` accepts and the countries a {@see Proxy} can use.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function catalog(?RequestOptions $requestOptions = null): Catalog
    {
        $response = $this->transport->send(
            'GET',
            $this->baseUrl . '/instant-snap/v1/catalog',
            $requestOptions->timeoutSeconds ?? $this->timeoutSeconds,
            ['X-Api-Key' => $this->apiKey()],
        );

        return new Catalog(HttpTransport::decode($response));
    }

    private function sendCapture(
        CaptureOptions $options,
        string $responseType,
        ?RequestOptions $requestOptions,
        bool $acceptJson = true,
        bool $async = false,
    ): ResponseInterface {
        $body = $options->jsonSerialize();
        $body->response_type = $responseType;
        if ($async) {
            $body->async = true;
        }

        return $this->transport->send(
            'POST',
            $this->baseUrl . '/instant-snap/v1/captures',
            // Nothing runs while an async submit waits for its answer, so it takes the ordinary timeout.
            $requestOptions->timeoutSeconds ?? ($async ? $this->timeoutSeconds : $this->captureTimeoutSeconds),
            [
                'X-Api-Key' => $this->apiKey(),
                'Idempotency-Key' => $requestOptions->idempotencyKey ?? HttpTransport::uuid(),
            ],
            $body,
            $acceptJson,
        );
    }

    private static function now(): float
    {
        return hrtime(true) / 1e9;
    }

    private function apiKey(): string
    {
        return $this->apiKey ?? throw new AuthenticationException(
            'No Instant Snap API key. Pass instantSnapApiKey to the Client or set WSW_INSTANT_SNAP_API_KEY.',
        );
    }
}
