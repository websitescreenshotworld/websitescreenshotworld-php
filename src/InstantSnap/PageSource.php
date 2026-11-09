<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * The page source kept alongside a capture.
 */
final readonly class PageSource
{
    /** A {@see PageSourceFormat} value. */
    public string $format;

    public ?string $contentType;

    public ?int $sizeBytes;

    /** Lowercase hex SHA-256. */
    public ?string $sha256;

    /** Short-lived and reissued by every {@see InstantSnapService::get()}. Null in webhooks. */
    public ?string $downloadUrl;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->format = Fields::string($data, 'format');
        $this->contentType = Fields::nullableString($data, 'content_type');
        $this->sizeBytes = Fields::nullableInt($data, 'size_bytes');
        $this->sha256 = Fields::nullableString($data, 'sha256');
        $this->downloadUrl = Fields::nullableString($data, 'download_url');
        $this->raw = $data;
    }
}
