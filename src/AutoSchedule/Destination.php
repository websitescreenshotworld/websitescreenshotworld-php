<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * One place a scheduled capture was delivered to.
 */
final readonly class Destination
{
    public int $slot;

    public string $name;

    /** For example `aws_s3`. */
    public string $provider;

    public string $status;

    public ?\DateTimeImmutable $completedAt;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->slot = Fields::int($data, 'slot');
        $this->name = Fields::string($data, 'name');
        $this->provider = Fields::string($data, 'provider');
        $this->status = Fields::string($data, 'status');
        $this->completedAt = Fields::nullableDateTime($data, 'completed_at');
        $this->raw = $data;
    }
}
