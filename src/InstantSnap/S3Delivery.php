<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * How the upload to your bucket is going.
 */
final readonly class S3Delivery
{
    /** A {@see S3DeliveryStatus} value. */
    public string $status;

    public string $bucket;

    /** The object key, once known. */
    public ?string $key;

    /** Masked to its last four characters; null in webhooks. */
    public ?string $accessKeyId;

    /** Why the upload failed. */
    public ?string $error;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->status = Fields::string($data, 'status');
        $this->bucket = Fields::string($data, 'bucket');
        $this->key = Fields::nullableString($data, 'key');
        $this->accessKeyId = Fields::nullableString($data, 'access_key_id');
        $this->error = Fields::nullableString($data, 'error');
        $this->raw = $data;
    }
}
