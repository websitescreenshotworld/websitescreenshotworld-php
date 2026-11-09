<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * A webhook's signature header is missing, malformed, too old or does not match the body.
 */
final class SignatureVerificationException extends WebsiteScreenshotWorldException {}
