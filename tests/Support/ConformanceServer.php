<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests\Support;

use GuzzleHttp\Client as GuzzleClient;

/**
 * Runs conformance/server.js, which plays back a fixture's responses and records what the client sent.
 */
final class ConformanceServer
{
    private const SCRIPT = __DIR__ . '/../../spec/conformance/server.js';

    /** @var resource */
    private $process;

    public readonly string $baseUrl;

    private readonly GuzzleClient $http;

    public function __construct()
    {
        $process = proc_open(['node', self::SCRIPT], [1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if ($process === false) {
            throw new \RuntimeException('Could not start the conformance server; is Node.js installed?');
        }
        $this->process = $process;

        $line = fgets($pipes[1]);
        if ($line === false || preg_match('#(http://127\.0\.0\.1:\d+)#', $line, $match) !== 1) {
            $this->stop();

            throw new \RuntimeException('The conformance server did not report its address.');
        }
        $this->baseUrl = $match[1];
        $this->http = new GuzzleClient(['base_uri' => $this->baseUrl, 'timeout' => 5]);
    }

    /**
     * Queues a fixture's responses and forgets earlier requests.
     *
     * @param list<mixed> $responses
     */
    public function load(array $responses): void
    {
        $this->http->post('/__load', ['json' => ['responses' => $responses]]);
    }

    /**
     * @return list<array<string, mixed>> What the client sent since the last load, in order.
     */
    public function requests(): array
    {
        $body = (string) $this->http->get('/__requests')->getBody();

        return array_map(Json::object(...), Json::list(json_decode($body, true, 512, \JSON_THROW_ON_ERROR)));
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }
}
