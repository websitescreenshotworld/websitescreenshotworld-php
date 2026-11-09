<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * The values a capture takes for options it does not send, and the limits it is checked against. Every field is
 * null when the API leaves it out; `raw` holds the whole answer.
 */
final readonly class Defaults
{
    public ?string $specVersion;

    public ?string $responseType;

    public ?string $mediaCategory;

    /**
     * The default format of each media category.
     *
     * @var array<string, string>|null
     */
    public ?array $format;

    /**
     * The formats each media category accepts.
     *
     * @var array<string, list<string>>|null
     */
    public ?array $formats;

    public ?string $deviceCategory;

    public ?string $emulatedDevice;

    public ?Viewport $viewport;

    public ?int $zoom;

    public ?string $scrollMode;

    public ?bool $autoHeight;

    public ?int $autoHeightMaxHeight;

    public ?int $autoHeightMaxHeightWebp;

    public ?bool $blockAds;

    public ?bool $blockPopUps;

    public ?bool $pressEscape;

    public ?int $pageTimeoutMs;

    public ?int $scrollingDelayMs;

    public ?bool $geolocation;

    public ?float $geolocationAccuracy;

    public ?bool $waitForCloudSync;

    /** @var array<mixed>|null */
    public ?array $timestampWatermark;

    /** @var array<mixed>|null */
    public ?array $pageSource;

    /** @var array<mixed>|null */
    public ?array $limits;

    /**
     * Settings the API does not set, so the browser's own behaviour applies.
     *
     * @var list<string>|null
     */
    public ?array $leftToTheBrowser;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->specVersion = Fields::nullableString($data, 'spec_version');
        $this->responseType = Fields::nullableString($data, 'response_type');
        $this->mediaCategory = Fields::nullableString($data, 'media_category');
        $this->format = Fields::nullableStringMap($data, 'format');
        $this->formats = self::formats($data);
        $this->deviceCategory = Fields::nullableString($data, 'device_category');
        $this->emulatedDevice = Fields::nullableString($data, 'emulated_device');
        $viewport = Fields::nullableObject($data, 'viewport');
        $this->viewport = $viewport === null ? null : new Viewport(
            Fields::nullableInt($viewport, 'width'),
            Fields::nullableInt($viewport, 'height'),
            Fields::nullableFloat($viewport, 'device_scale_factor'),
        );
        $this->zoom = Fields::nullableInt($data, 'zoom');
        $this->scrollMode = Fields::nullableString($data, 'scroll_mode');
        $this->autoHeight = Fields::nullableBool($data, 'auto_height');
        $this->autoHeightMaxHeight = Fields::nullableInt($data, 'auto_height_max_height');
        $this->autoHeightMaxHeightWebp = Fields::nullableInt($data, 'auto_height_max_height_webp');
        $this->blockAds = Fields::nullableBool($data, 'block_ads');
        $this->blockPopUps = Fields::nullableBool($data, 'block_pop_ups');
        $this->pressEscape = Fields::nullableBool($data, 'press_escape');
        $this->pageTimeoutMs = Fields::nullableInt($data, 'page_timeout_ms');
        $this->scrollingDelayMs = Fields::nullableInt($data, 'scrolling_delay_ms');
        $this->geolocation = Fields::nullableBool($data, 'geolocation');
        $this->geolocationAccuracy = Fields::nullableFloat($data, 'geolocation_accuracy');
        $this->waitForCloudSync = Fields::nullableBool($data, 'wait_for_cloud_sync');
        $this->timestampWatermark = Fields::nullableObject($data, 'timestamp_watermark');
        $this->pageSource = Fields::nullableObject($data, 'page_source');
        $this->limits = Fields::nullableObject($data, 'limits');
        $this->leftToTheBrowser = Fields::nullableStringList($data, 'left_to_the_browser');
        $this->raw = $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, list<string>>|null
     */
    private static function formats(array $data): ?array
    {
        $formats = Fields::nullableObject($data, 'formats');
        if ($formats === null) {
            return null;
        }

        $byCategory = [];
        foreach (array_keys($formats) as $category) {
            $byCategory[(string) $category] = Fields::stringList($formats, (string) $category);
        }

        return $byCategory;
    }
}
