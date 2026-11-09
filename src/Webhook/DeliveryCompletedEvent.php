<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\AutoSchedule\DeliveredCapture;
use WebsiteScreenshotWorld\Internal\Fields;

/**
 * `delivery.completed` from Auto Schedule: a capture reached its destinations.
 */
final readonly class DeliveryCompletedEvent extends WebhookEvent
{
    public DeliveredCapture $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = new DeliveredCapture(Fields::object($envelope, 'data'));
    }
}
