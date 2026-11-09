<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * The answer to {@see AutoScheduleService::triggerNow()}.
 */
final readonly class TriggerResult
{
    /** A {@see TriggerStatus} value. */
    public string $status;

    public string $webPageId;

    /** A {@see TriggeredBy} value. */
    public string $triggeredBy;

    /** When the capture was queued; for `already_pending`, the original trigger. */
    public \DateTimeImmutable $triggeredAt;

    /** One sentence for a person. */
    public string $message;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->status = Fields::string($data, 'status');
        $this->webPageId = Fields::string($data, 'web_page_id');
        $this->triggeredBy = Fields::string($data, 'triggered_by');
        $this->triggeredAt = Fields::dateTime($data, 'triggered_at');
        $this->message = Fields::string($data, 'message');
        $this->raw = $data;
    }
}
