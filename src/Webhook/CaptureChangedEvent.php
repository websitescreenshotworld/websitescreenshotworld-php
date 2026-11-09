<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\AutoSchedule\ChangedCapture;
use WebsiteScreenshotWorld\Internal\Fields;

/**
 * `capture.changed` from Auto Schedule: a capture differs from the one before it.
 */
final readonly class CaptureChangedEvent extends WebhookEvent
{
    public ChangedCapture $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = new ChangedCapture(Fields::object($envelope, 'data'));
    }
}
