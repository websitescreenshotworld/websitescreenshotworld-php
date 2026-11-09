<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * The page source kept with a scheduled capture.
 */
final readonly class PageSource
{
    /** A {@see \WebsiteScreenshotWorld\InstantSnap\PageSourceFormat} value. */
    public string $format;

    /** Lowercase hex SHA-256. */
    public ?string $sha256;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->format = Fields::string($data, 'format');
        $this->sha256 = Fields::nullableString($data, 'sha256');
        $this->raw = $data;
    }
}
