<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Exception;

/**
 * The base of every error this library throws.
 *
 * An API refusal carries its `error_code`; switch on that, never on the status or the message. A code this
 * version of the library does not know is thrown as this class itself.
 */
class WebsiteScreenshotWorldException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $errorCode = null,
        private readonly ?int $httpStatus = null,
        private readonly ?int $retryAfterSeconds = null,
        private readonly ?string $rawBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The API's error token, such as `rate_limited`. Null when no answer came back.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * How long the API asked to wait before trying again, from the body or the Retry-After header.
     */
    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * The response body exactly as received.
     */
    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }
}
