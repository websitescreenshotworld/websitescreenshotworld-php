<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

/**
 * Known values of the response type a capture was taken with.
 */
final class ResponseType
{
    public const JSON = 'json';
    public const BY_FORMAT = 'by_format';
    public const CLOUD_STORAGE = 'cloud_storage';

    private function __construct() {}
}
