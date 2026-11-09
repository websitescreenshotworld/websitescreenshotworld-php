<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * The service could not handle the request right now; thrown once the retries have run out.
 */
final class ServiceException extends WebsiteScreenshotWorldException {}
