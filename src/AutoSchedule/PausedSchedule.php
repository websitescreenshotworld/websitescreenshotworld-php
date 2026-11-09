<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * A schedule the service paused, as the schedule.paused webhook carries it.
 */
final readonly class PausedSchedule
{
    public string $scheduleId;

    public string $scheduleName;

    public string $url;

    /** An error token, such as `quota_exhausted`. */
    public string $reason;

    public \DateTimeImmutable $pausedAt;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->scheduleId = Fields::string($data, 'schedule_id');
        $this->scheduleName = Fields::string($data, 'schedule_name');
        $this->url = Fields::string($data, 'url');
        $this->reason = Fields::string($data, 'reason');
        $this->pausedAt = Fields::dateTime($data, 'paused_at');
        $this->raw = $data;
    }
}
