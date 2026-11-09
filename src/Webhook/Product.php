<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

/**
 * The product a webhook event comes from.
 */
final class Product
{
    public const INSTANT_SNAP = 'instant_snap';
    public const AUTO_SCHEDULE = 'auto_schedule';

    private function __construct() {}
}
