<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * The S3-compatible bucket {@see InstantSnapService::captureToBucket()} uploads to. Needs `region` or
 * `endpointUrl`.
 */
final readonly class S3DeliveryOptions implements \JsonSerializable
{
    /**
     * @param string $secretAccessKey Sent to the API and never returned.
     * @param string|null $endpointUrl For providers other than AWS; https only.
     * @param bool|null $waitForSync Wait up to 30 seconds for the upload before answering.
     */
    public function __construct(
        public string $accessKeyId,
        #[\SensitiveParameter]
        public string $secretAccessKey,
        public string $bucket,
        public ?string $region = null,
        public ?string $endpointUrl = null,
        public ?bool $forcePathStyle = null,
        public ?string $keyPrefix = null,
        public ?bool $waitForSync = null,
    ) {}

    public function jsonSerialize(): \stdClass
    {
        return Wire::object([
            'access_key_id' => $this->accessKeyId,
            'secret_access_key' => $this->secretAccessKey,
            'bucket' => $this->bucket,
            'region' => $this->region,
            'endpoint_url' => $this->endpointUrl,
            'force_path_style' => $this->forcePathStyle,
            'key_prefix' => $this->keyPrefix,
            'wait_for_sync' => $this->waitForSync,
        ]);
    }

    /**
     * @return array<mixed>
     */
    public function __debugInfo(): array
    {
        return array_merge(get_object_vars($this), ['secretAccessKey' => '********']);
    }
}
