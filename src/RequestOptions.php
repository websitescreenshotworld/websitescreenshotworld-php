<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld;

/**
 * Settings for a single call.
 */
final readonly class RequestOptions
{
    /**
     * @param string|null $idempotencyKey Sent as the capture's Idempotency-Key instead of a random one, so a
     *                                    repeated call within 24 hours returns the first capture. Captures only.
     * @param float|null $timeoutSeconds How long the call may take, retries included. Each request is bounded by it
     *                                   only when the library chose the HTTP client itself; a client you pass in
     *                                   keeps its own timeouts.
     */
    public function __construct(
        public ?string $idempotencyKey = null,
        public ?float $timeoutSeconds = null,
    ) {}
}
