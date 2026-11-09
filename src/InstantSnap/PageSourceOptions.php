<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * Also keep the page source. Charged as a second capture, capped at 10 MB, not available with `captureFile()`.
 */
final readonly class PageSourceOptions implements \JsonSerializable
{
    /**
     * @param string|null $format A {@see PageSourceFormat} value; the API default is `html`.
     */
    public function __construct(
        public ?string $format = null,
    ) {}

    public function jsonSerialize(): \stdClass
    {
        return Wire::object(['format' => $this->format]);
    }
}
