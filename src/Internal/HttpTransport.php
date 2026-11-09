<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Internal;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use WebsiteScreenshotWorld\Exception\ConnectionException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Version;

/**
 * Sends a request with the library's headers and retries it as spec/README.md describes.
 *
 * @internal
 */
final class HttpTransport
{
    private const MAX_WAIT_SECONDS = 60.0;

    private readonly string $userAgent;

    /**
     * @param \Closure(float): void $sleep
     */
    public function __construct(
        private readonly HttpClients $clients,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly int $maxRetries,
        private readonly \Closure $sleep,
    ) {
        $this->userAgent = \sprintf(
            'websitescreenshotworld-php/%s (PHP %d.%d.%d)',
            Version::CURRENT,
            \PHP_MAJOR_VERSION,
            \PHP_MINOR_VERSION,
            \PHP_RELEASE_VERSION,
        );
    }

    /**
     * Sends the request and returns a 2xx answer, or throws once the retries have run out.
     *
     * @param array<string, string> $headers
     */
    public function send(
        string $method,
        string $url,
        float $timeoutSeconds,
        array $headers = [],
        ?\stdClass $json = null,
        bool $acceptJson = true,
    ): ResponseInterface {
        $request = $this->requestFactory->createRequest($method, $url)->withHeader('User-Agent', $this->userAgent);
        if ($acceptJson) {
            $request = $request->withHeader('Accept', 'application/json');
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($json !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(self::encode($json)));
        }

        // The timeout bounds the whole call: every attempt and the waits between them.
        $deadline = self::now() + $timeoutSeconds;
        for ($attempt = 0; ; $attempt++) {
            $client = $attempt === 0
                ? $this->clients->forTimeout($timeoutSeconds)
                : $this->clients->forRemaining($deadline - self::now());

            try {
                $response = $client->sendRequest(self::rewound($request));
            } catch (ClientExceptionInterface $e) {
                if (self::now() >= $deadline) {
                    // Running out of time ends the call; it is not retried.
                    throw new ConnectionException(
                        \sprintf('No answer from %s within %s seconds.', self::describe($url), self::seconds($timeoutSeconds)),
                        previous: $e,
                    );
                }
                $failure = new ConnectionException(\sprintf('Could not reach %s: %s', self::describe($url), $e->getMessage()), previous: $e);
                if ($attempt >= $this->maxRetries) {
                    throw $failure;
                }
                $this->waitOrGiveUp(self::backoff($attempt), $deadline, $failure);

                continue;
            }

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $response;
            }

            // A redirect lands here too: the library's own clients never follow one, so the API key stays put.
            $exception = Refusal::toException($response);
            if ($attempt >= $this->maxRetries || !Refusal::isRetryable($exception)) {
                throw $exception;
            }
            $this->waitOrGiveUp(self::waitAfter($exception, $attempt), $deadline, $exception);
        }
    }

    /**
     * Waits before a retry, or throws the failure when the wait would run past the call's deadline.
     */
    private function waitOrGiveUp(float $wait, float $deadline, WebsiteScreenshotWorldException $failure): void
    {
        if (self::now() + $wait >= $deadline) {
            throw $failure;
        }
        ($this->sleep)($wait);
    }

    private static function now(): float
    {
        return hrtime(true) / 1e9;
    }

    /**
     * The URL without its query, which for a download link holds the signature.
     */
    private static function describe(string $url): string
    {
        $query = strpos($url, '?');

        return $query === false ? $url : substr($url, 0, $query);
    }

    private static function seconds(float $seconds): string
    {
        return rtrim(rtrim(\sprintf('%.3f', $seconds), '0'), '.');
    }

    /**
     * Pauses for the given number of seconds, with the same sleep the retries use.
     */
    public function sleep(float $seconds): void
    {
        ($this->sleep)($seconds);
    }

    /**
     * Reads a 2xx answer as a JSON object, without the `success` flag.
     *
     * @return array<mixed>
     */
    public static function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        }

        if (!\is_array($decoded) || array_is_list($decoded)) {
            throw new WebsiteScreenshotWorldException(
                \sprintf('The API answered HTTP %d with a body that is not a JSON object.', $response->getStatusCode()),
                httpStatus: $response->getStatusCode(),
                rawBody: $body,
            );
        }
        unset($decoded['success']);

        return $decoded;
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function encode(\stdClass $json): string
    {
        return json_encode($json, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private static function rewound(RequestInterface $request): RequestInterface
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $request;
    }

    private static function waitAfter(WebsiteScreenshotWorldException $exception, int $attempt): float
    {
        $hint = $exception->getRetryAfterSeconds();

        return $hint === null ? self::backoff($attempt) : min((float) $hint, self::MAX_WAIT_SECONDS);
    }

    /**
     * 0.5 s, then 1 s, doubling from there, each with up to 25 % jitter.
     */
    private static function backoff(int $attempt): float
    {
        $base = min(0.5 * 2 ** $attempt, self::MAX_WAIT_SECONDS);

        return $base * (1 + random_int(0, 250) / 1000);
    }
}
