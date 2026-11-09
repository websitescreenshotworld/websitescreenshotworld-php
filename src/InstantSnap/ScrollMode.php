<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

/**
 * Known values of a scroll mode. `SCROLL` captures the full page.
 */
final class ScrollMode
{
    public const VIEWPORT = 'viewport';
    public const SCROLL = 'scroll';

    private function __construct() {}
}
