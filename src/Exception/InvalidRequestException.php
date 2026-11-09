<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * The request was not valid, for example an option out of range or a reused Idempotency-Key.
 */
final class InvalidRequestException extends WebsiteScreenshotWorldException {}
