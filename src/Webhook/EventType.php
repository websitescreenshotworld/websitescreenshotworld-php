<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

/**
 * Known webhook event types.
 */
final class EventType
{
    public const CAPTURE_SUCCEEDED = 'capture.succeeded';
    public const CAPTURE_FAILED = 'capture.failed';
    public const DELIVERY_COMPLETED = 'delivery.completed';
    public const CAPTURE_CHANGED = 'capture.changed';
    public const SCHEDULE_PAUSED = 'schedule.paused';
    public const SITEMAP_RUN_COMPLETED = 'sitemap_run.completed';
    public const PING = 'ping';

    private function __construct() {}
}
