<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

/**
 * Known values of a capture status. `DELIVERED` is the end state of a file returned by `captureFile()`. An async
 * capture from `submit()` or `submitToBucket()` goes `QUEUED`, then `RUNNING`, then `SUCCEEDED` or `FAILED`.
 */
final class CaptureStatus
{
    /** Accepted by `submit()` or `submitToBucket()`, waiting for a browser. */
    public const QUEUED = 'queued';
    /** An async capture a browser is working on. */
    public const RUNNING = 'running';
    public const PENDING = 'pending';
    public const SUCCEEDED = 'succeeded';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';
    public const TIMED_OUT = 'timed_out';
    public const EXPIRED = 'expired';

    private function __construct() {}
}
