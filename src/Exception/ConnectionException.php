<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * The request could not be sent or no answer came back, after the retries ran out.
 */
final class ConnectionException extends WebsiteScreenshotWorldException {}
