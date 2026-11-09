<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

/**
 * Known values of a trigger-now status. `ALREADY_PENDING` means nothing new was started or charged.
 */
final class TriggerStatus
{
    public const TRIGGERED = 'triggered';
    public const ALREADY_PENDING = 'already_pending';

    private function __construct() {}
}
