<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * The API key is missing, malformed, revoked or belongs to the other product.
 */
final class AuthenticationException extends WebsiteScreenshotWorldException {}
