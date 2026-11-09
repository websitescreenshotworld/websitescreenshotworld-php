<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

/**
 * Known values of a bucket upload status.
 */
final class S3DeliveryStatus
{
    public const NOT_STARTED = 'not_started';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    private function __construct() {}
}
