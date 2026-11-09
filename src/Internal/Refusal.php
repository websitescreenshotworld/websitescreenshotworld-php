<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Internal;

use Psr\Http\Message\ResponseInterface;
use WebsiteScreenshotWorld\Exception\AuthenticationException;
use WebsiteScreenshotWorld\Exception\CaptureException;
use WebsiteScreenshotWorld\Exception\InvalidRequestException;
use WebsiteScreenshotWorld\Exception\NotFoundException;
use WebsiteScreenshotWorld\Exception\PlanLimitException;
use WebsiteScreenshotWorld\Exception\RateLimitException;
use WebsiteScreenshotWorld\Exception\ServiceException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;

/**
 * Turns a refusal into the exception for its `error_code`, following spec/errors.json.
 *
 * @internal
 */
final class Refusal
{
    private const EXCEPTIONS = [
        'api_key_invalid' => AuthenticationException::class,
        'validation_failed' => InvalidRequestException::class,
        's3_destination_invalid' => InvalidRequestException::class,
        'web_page_id_invalid' => InvalidRequestException::class,
        'idempotency_key_reused' => InvalidRequestException::class,
        'sitemap_schedule_not_triggerable' => InvalidRequestException::class,
        'quota_exhausted' => PlanLimitException::class,
        'feature_not_available' => PlanLimitException::class,
        'no_trigger_allowance' => PlanLimitException::class,
        'forbidden' => PlanLimitException::class,
        'capture_not_found' => NotFoundException::class,
        'web_page_not_found' => NotFoundException::class,
        'not_found' => NotFoundException::class,
        'rate_limited' => RateLimitException::class,
        // Not retried: the organization's async queue is full, and a retry would only meet the same queue.
        'queue_full' => RateLimitException::class,
        'capture_failed' => CaptureException::class,
        'capture_timeout' => CaptureException::class,
        'payload_too_large' => CaptureException::class,
        'capture_expired' => CaptureException::class,
        'capture_busy' => ServiceException::class,
        'service_unavailable' => ServiceException::class,
        'internal_error' => ServiceException::class,
        'request_cancelled' => ServiceException::class,
        'idempotency_key_in_use' => ServiceException::class,
    ];

    private const RETRYABLE = ['rate_limited', 'capture_busy', 'service_unavailable', 'idempotency_key_in_use'];

    /** Used when a proxy or load balancer answered without the API's JSON error body. */
    private const CODE_BY_STATUS = [
        400 => 'validation_failed',
        401 => 'api_key_invalid',
        403 => 'forbidden',
        404 => 'not_found',
        409 => 'idempotency_key_in_use',
        413 => 'payload_too_large',
        429 => 'rate_limited',
        500 => 'internal_error',
        502 => 'service_unavailable',
        503 => 'service_unavailable',
        504 => 'service_unavailable',
    ];

    private function __construct() {}

    public static function toException(ResponseInterface $response): WebsiteScreenshotWorldException
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $error = self::errorBody($body);

        $code = \is_string($error['error_code'] ?? null) ? $error['error_code'] : (self::CODE_BY_STATUS[$status] ?? 'unknown_error');
        $message = \is_string($error['error_message'] ?? null) ? $error['error_message'] : null;
        $retryAfter = \is_int($error['retry_after_seconds'] ?? null) ? $error['retry_after_seconds'] : null;

        $class = self::EXCEPTIONS[$code] ?? WebsiteScreenshotWorldException::class;

        return new $class(
            $message ?? \sprintf('The API answered HTTP %d without an error body.', $status),
            errorCode: $code,
            httpStatus: $status,
            retryAfterSeconds: $retryAfter ?? self::retryAfterHeader($response),
            rawBody: $body,
        );
    }

    public static function isRetryable(WebsiteScreenshotWorldException $exception): bool
    {
        return \in_array($exception->getErrorCode(), self::RETRYABLE, true);
    }

    /**
     * @return array<mixed>|null
     */
    private static function errorBody(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    private static function retryAfterHeader(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        return ctype_digit($value) ? (int) $value : null;
    }
}
