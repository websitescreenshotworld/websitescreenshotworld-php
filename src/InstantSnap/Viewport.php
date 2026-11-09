<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * The browser window size. Unset fields take the API defaults (1920 x 1080 at scale 1).
 */
final readonly class Viewport implements \JsonSerializable
{
    /**
     * @param int|null $width CSS pixels, 250 to 7680.
     * @param int|null $height CSS pixels, 250 to 7680.
     * @param float|null $deviceScaleFactor Device pixel ratio, 1 to 3.
     */
    public function __construct(
        public ?int $width = null,
        public ?int $height = null,
        public ?float $deviceScaleFactor = null,
    ) {}

    public function jsonSerialize(): \stdClass
    {
        return Wire::object([
            'width' => $this->width,
            'height' => $this->height,
            'device_scale_factor' => $this->deviceScaleFactor,
        ]);
    }
}
