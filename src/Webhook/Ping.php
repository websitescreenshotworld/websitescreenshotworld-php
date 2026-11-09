<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * The body of a {@see PingEvent}.
 */
final readonly class Ping
{
    public string $endpointId;

    public string $endpointName;

    public string $message;

    public \DateTimeImmutable $sentAt;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->endpointId = Fields::string($data, 'endpoint_id');
        $this->endpointName = Fields::string($data, 'endpoint_name');
        $this->message = Fields::string($data, 'message');
        $this->sentAt = Fields::dateTime($data, 'sent_at');
        $this->raw = $data;
    }
}
