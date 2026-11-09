<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * Capture through a proxy in another country. Needs the ProxyLocation plan feature.
 */
final readonly class Proxy implements \JsonSerializable
{
    /**
     * @param string $country A country from {@see InstantSnapService::catalog()}.
     */
    public function __construct(
        public string $country,
    ) {}

    public function jsonSerialize(): \stdClass
    {
        return Wire::object(['country' => $this->country]);
    }
}
