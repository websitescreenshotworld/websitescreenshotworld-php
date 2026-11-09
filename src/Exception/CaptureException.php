<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * The website could not be captured, did not finish in time, or the file is too large to return.
 */
final class CaptureException extends WebsiteScreenshotWorldException {}
