<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\AutoSchedule\PausedSchedule;
use WebsiteScreenshotWorld\Internal\Fields;

/**
 * `schedule.paused` from Auto Schedule.
 */
final readonly class SchedulePausedEvent extends WebhookEvent
{
    public PausedSchedule $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = new PausedSchedule(Fields::object($envelope, 'data'));
    }
}
