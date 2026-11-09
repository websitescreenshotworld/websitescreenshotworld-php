<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * Too many requests; thrown once the retries have run out.
 */
final class RateLimitException extends WebsiteScreenshotWorldException {}
