<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * A capture, as {@see InstantSnapService::capture()} and {@see InstantSnapService::get()} return it and as
 * Instant Snap webhooks carry it.
 */
final readonly class Capture
{
    public string $id;

    /**
     * A {@see CaptureStatus} value. An async capture is `queued` or `running` until it ends `succeeded` or
     * `failed`.
     */
    public string $status;

    /** A {@see ResponseType} value. */
    public string $responseType;

    /** A {@see MediaCategory} value. Null while an async capture is `queued` or `running`. */
    public ?string $mediaCategory;

    /** The {@see Format} written. */
    public ?string $format;

    public ?string $contentType;

    public ?int $sizeBytes;

    public ?int $width;

    public ?int $height;

    /** How long the capture took. */
    public ?int $durationMs;

    /** The status the website answered with. */
    public ?int $pageHttpStatus;

    public ?\DateTimeImmutable $capturedAt;

    /** Where the browser ended up, when the page redirected. */
    public ?string $finalUrl;

    /** Lowercase hex SHA-256 of the file. */
    public ?string $mediaSha256;

    /**
     * Valid for 60 minutes and reissued by every {@see InstantSnapService::get()}. Null for bucket uploads and
     * in webhooks.
     */
    public ?string $downloadUrl;

    /** When the file is deleted, 24 hours after the capture. */
    public ?\DateTimeImmutable $expiresAt;

    public ?PageSource $pageSource;

    /** Present for {@see InstantSnapService::captureToBucket()} only. */
    public ?S3Delivery $s3Delivery;

    public ?string $callbackUrl;

    /** Why a failed or timed-out capture ended, such as `capture_expired` for an async capture left queued. */
    public ?string $errorCode;

    /** The default set the capture was taken under. */
    public string $specVersion;

    /**
     * The answer as received, including fields this version of the library does not know.
     *
     * @var array<mixed>
     */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->id = Fields::string($data, 'id');
        $this->status = Fields::string($data, 'status');
        $this->responseType = Fields::string($data, 'response_type');
        $this->mediaCategory = Fields::nullableString($data, 'media_category');
        $this->format = Fields::nullableString($data, 'format');
        $this->contentType = Fields::nullableString($data, 'content_type');
        $this->sizeBytes = Fields::nullableInt($data, 'size_bytes');
        $this->width = Fields::nullableInt($data, 'width');
        $this->height = Fields::nullableInt($data, 'height');
        $this->durationMs = Fields::nullableInt($data, 'duration_ms');
        $this->pageHttpStatus = Fields::nullableInt($data, 'page_http_status');
        $this->capturedAt = Fields::nullableDateTime($data, 'captured_at');
        $this->finalUrl = Fields::nullableString($data, 'final_url');
        $this->mediaSha256 = Fields::nullableString($data, 'media_sha256');
        $this->downloadUrl = Fields::nullableString($data, 'download_url');
        $this->expiresAt = Fields::nullableDateTime($data, 'expires_at');
        $pageSource = Fields::nullableObject($data, 'page_source');
        $this->pageSource = $pageSource === null ? null : new PageSource($pageSource);
        $s3Delivery = Fields::nullableObject($data, 's3_delivery');
        $this->s3Delivery = $s3Delivery === null ? null : new S3Delivery($s3Delivery);
        $this->callbackUrl = Fields::nullableString($data, 'callback_url');
        $this->errorCode = Fields::nullableString($data, 'error_code');
        $this->specVersion = Fields::string($data, 'spec_version');
        $this->raw = $data;
    }
}
