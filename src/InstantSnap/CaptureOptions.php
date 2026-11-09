<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * What to capture and how. Only `url` is required; an option left as null is not sent and takes the API
 * default, while `false`, `0` and `""` are sent as given. {@see InstantSnapService::defaults()} lists the
 * defaults and limits.
 */
final readonly class CaptureOptions implements \JsonSerializable
{
    /**
     * @param string $url Absolute http or https URL, without credentials, at most 2048 characters.
     * @param string|null $mediaCategory A {@see MediaCategory} value; the API default is `image`.
     * @param string|null $format A {@see Format} value belonging to the media category.
     * @param string|null $deviceCategory A {@see DeviceCategory} value; the API default is `desktop`.
     * @param string|null $emulatedDevice A device from {@see InstantSnapService::catalog()}, case-insensitive.
     * @param string|null $scrollMode A {@see ScrollMode} value; `scroll` captures the full page.
     * @param int|null $maxHeight 250 to 100000; refused above the format's ceiling.
     * @param int|null $zoom Percent, 50 to 100.
     * @param int|null $pageTimeoutMs 1000 to 60000.
     * @param int|null $scrollingDelayMs The pause after each scroll step of a full-page capture, 0 to 10000;
     *                                     the API default is 500.
     * @param string|null $locale BCP 47 language tag.
     * @param string|null $timeZone IANA time zone.
     * @param array<string, string>|null $headers Sent with every request the page makes; names are sent exactly
     *                                            as given. Needs the RequestHeadersCookies plan feature.
     * @param array<string, string>|null $cookies Set for the URL's origin; names are sent exactly as given.
     *                                            Needs the RequestHeadersCookies plan feature.
     * @param S3DeliveryOptions|null $s3Delivery Required by {@see InstantSnapService::captureToBucket()} and
     *                                           {@see InstantSnapService::submitToBucket()}, and refused by the
     *                                           other capture methods.
     * @param string|null $callbackUrl Also receives the capture.succeeded or capture.failed webhook. Needs the
     *                                 Webhooks plan feature.
     * @param int|null $timeout Async only ({@see InstantSnapService::submit()} and
     *                          {@see InstantSnapService::submitToBucket()}): the browser time the capture may
     *                          use, 30 to 300 seconds; the server default is 120. The server refuses it on the
     *                          other capture methods.
     * @param bool|null $pressEscape Press Escape after the pop-up pass and again after scrolling (once in a
     *                               video), for a modal that closes on Escape; the API default is false.
     */
    public function __construct(
        public string $url,
        public ?string $mediaCategory = null,
        public ?string $format = null,
        public ?Viewport $viewport = null,
        public ?string $deviceCategory = null,
        public ?string $emulatedDevice = null,
        public ?string $scrollMode = null,
        public ?bool $autoHeight = null,
        public ?int $maxHeight = null,
        public ?int $zoom = null,
        public ?bool $blockAds = null,
        public ?bool $blockPopUps = null,
        public ?bool $pressEscape = null,
        public ?int $pageTimeoutMs = null,
        public ?int $scrollingDelayMs = null,
        public ?string $userAgent = null,
        public ?string $locale = null,
        public ?string $timeZone = null,
        public ?array $headers = null,
        public ?array $cookies = null,
        public ?Geolocation $geolocation = null,
        public ?Selectors $selectors = null,
        public ?Proxy $proxy = null,
        public ?TimestampWatermark $timestampWatermark = null,
        public ?PageSourceOptions $pageSource = null,
        public ?S3DeliveryOptions $s3Delivery = null,
        public ?string $callbackUrl = null,
        public ?int $timeout = null,
    ) {}

    /**
     * The request body, in the API's field names, without `response_type` and `async`.
     */
    public function jsonSerialize(): \stdClass
    {
        return Wire::object([
            'url' => $this->url,
            'media_category' => $this->mediaCategory,
            'format' => $this->format,
            'viewport' => $this->viewport,
            'device_category' => $this->deviceCategory,
            'emulated_device' => $this->emulatedDevice,
            'scroll_mode' => $this->scrollMode,
            'auto_height' => $this->autoHeight,
            'max_height' => $this->maxHeight,
            'zoom' => $this->zoom,
            'block_ads' => $this->blockAds,
            'block_pop_ups' => $this->blockPopUps,
            'press_escape' => $this->pressEscape,
            'page_timeout_ms' => $this->pageTimeoutMs,
            'scrolling_delay_ms' => $this->scrollingDelayMs,
            'user_agent' => $this->userAgent,
            'locale' => $this->locale,
            'time_zone' => $this->timeZone,
            'headers' => Wire::map($this->headers),
            'cookies' => Wire::map($this->cookies),
            'geolocation' => $this->geolocation,
            'selectors' => $this->selectors,
            'proxy' => $this->proxy,
            'timestamp_watermark' => $this->timestampWatermark,
            'page_source' => $this->pageSource,
            's3_delivery' => $this->s3Delivery,
            'callback_url' => $this->callbackUrl,
            'timeout' => $this->timeout,
        ]);
    }
}
