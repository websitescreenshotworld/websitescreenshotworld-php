<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * The position the browser reports to the page.
 */
final readonly class Geolocation implements \JsonSerializable
{
    /**
     * @param float $latitude -90 to 90.
     * @param float $longitude -180 to 180.
     * @param float|null $accuracy Metres, 1 to 10000; the API default is 100.
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?float $accuracy = null,
    ) {}

    public function jsonSerialize(): \stdClass
    {
        return Wire::object([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
        ]);
    }
}
