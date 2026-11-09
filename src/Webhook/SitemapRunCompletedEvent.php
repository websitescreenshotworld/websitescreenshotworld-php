<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\AutoSchedule\SitemapRun;
use WebsiteScreenshotWorld\Internal\Fields;

/**
 * `sitemap_run.completed` from Auto Schedule.
 */
final readonly class SitemapRunCompletedEvent extends WebhookEvent
{
    public SitemapRun $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = new SitemapRun(Fields::object($envelope, 'data'));
    }
}
