<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * `ping`: a test delivery, from either product.
 */
final readonly class PingEvent extends WebhookEvent
{
    public Ping $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = new Ping(Fields::object($envelope, 'data'));
    }
}
