<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\AutoSchedule\ScheduledCapture;
use WebsiteScreenshotWorld\Internal\Fields;

/**
 * `capture.failed` from Auto Schedule.
 */
final readonly class AutoScheduleCaptureFailedEvent extends WebhookEvent
{
    public ScheduledCapture $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = new ScheduledCapture(Fields::object($envelope, 'data'));
    }
}
