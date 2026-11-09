<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Internal;

use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Hands out the PSR-18 client for a given timeout.
 *
 * PSR-18 has no per-request timeout. A client passed in by the caller is used as it is, with its own timeouts and
 * redirect settings. When the library discovers the client itself, it configures Guzzle or Symfony HttpClient with
 * the timeout, because their defaults either never time out or give up on a capture that is still running, and
 * turns redirects off, so the API key is never sent to another host.
 *
 * @internal
 */
final class HttpClients
{
    private ?ClientInterface $discovered = null;

    /** @var array<array-key, ClientInterface> */
    private array $byTimeout = [];

    public function __construct(private readonly ?ClientInterface $given) {}

    /**
     * The client for a call's first attempt, kept for the next call with the same timeout.
     */
    public function forTimeout(float $seconds): ClientInterface
    {
        if ($this->given !== null) {
            return $this->given;
        }

        return $this->byTimeout[(string) $seconds] ??= $this->configure($this->discovered(), $seconds);
    }

    /**
     * The client for a retry, bounded by the time the call has left.
     */
    public function forRemaining(float $seconds): ClientInterface
    {
        if ($this->given !== null) {
            return $this->given;
        }

        return $this->configure($this->discovered(), $seconds);
    }

    private function discovered(): ClientInterface
    {
        return $this->discovered ??= Psr18ClientDiscovery::find();
    }

    private function configure(ClientInterface $client, float $seconds): ClientInterface
    {
        if ($client instanceof GuzzleClient) {
            return new GuzzleClient(['timeout' => $seconds, 'allow_redirects' => false]);
        }

        // Symfony's "timeout" is the idle time between bytes; "max_duration" bounds the whole request.
        if (str_starts_with($client::class, 'Symfony\\Component\\HttpClient\\') && method_exists($client, 'withOptions')) {
            $configured = $client->withOptions(['timeout' => $seconds, 'max_duration' => $seconds, 'max_redirects' => 0]);
            if ($configured instanceof ClientInterface) {
                return $configured;
            }
        }

        return $client;
    }
}
