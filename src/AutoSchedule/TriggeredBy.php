<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

/**
 * Known values of what started a scheduled capture.
 */
final class TriggeredBy
{
    public const API = 'api';
    public const UI = 'ui';

    private function __construct() {}
}
