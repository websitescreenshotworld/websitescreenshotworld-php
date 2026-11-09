<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * A strip showing the capture time and URL. Passing this block turns it on. Not available for video.
 */
final readonly class TimestampWatermark implements \JsonSerializable
{
    /**
     * @param string|null $location A {@see WatermarkLocation} value; the API default is `top`.
     * @param string|null $fontColor Hex colour; the API default is `#000000`.
     * @param string|null $backgroundColor Hex colour; the API default is `#FFFFFF`.
     */
    public function __construct(
        public ?bool $enabled = null,
        public ?string $location = null,
        public ?string $fontColor = null,
        public ?string $backgroundColor = null,
    ) {}

    public function jsonSerialize(): \stdClass
    {
        return Wire::object([
            'enabled' => $this->enabled,
            'location' => $this->location,
            'font_color' => $this->fontColor,
            'background_color' => $this->backgroundColor,
        ]);
    }
}
