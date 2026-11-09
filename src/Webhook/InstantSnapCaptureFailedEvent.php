<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\InstantSnap\Capture;
use WebsiteScreenshotWorld\Internal\Fields;

/**
 * `capture.failed` from Instant Snap. `$data->errorCode` says why.
 */
final readonly class InstantSnapCaptureFailedEvent extends WebhookEvent
{
    public Capture $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = new Capture(Fields::object($envelope, 'data'));
    }
}
