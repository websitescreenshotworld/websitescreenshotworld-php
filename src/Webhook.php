<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld;

use WebsiteScreenshotWorld\Exception\SignatureVerificationException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Webhook\AutoScheduleCaptureFailedEvent;
use WebsiteScreenshotWorld\Webhook\AutoScheduleCaptureSucceededEvent;
use WebsiteScreenshotWorld\Webhook\CaptureChangedEvent;
use WebsiteScreenshotWorld\Webhook\DeliveryCompletedEvent;
use WebsiteScreenshotWorld\Webhook\InstantSnapCaptureFailedEvent;
use WebsiteScreenshotWorld\Webhook\InstantSnapCaptureSucceededEvent;
use WebsiteScreenshotWorld\Webhook\PingEvent;
use WebsiteScreenshotWorld\Webhook\SchedulePausedEvent;
use WebsiteScreenshotWorld\Webhook\SitemapRunCompletedEvent;
use WebsiteScreenshotWorld\Webhook\UnknownEvent;
use WebsiteScreenshotWorld\Webhook\WebhookEvent;

/**
 * Verifies webhook deliveries from both products.
 */
final class Webhook
{
    /** The request header that carries the signature. */
    public const SIGNATURE_HEADER = 'X-WSW-Signature';

    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /** @var array<string, class-string<WebhookEvent>> */
    private const EVENTS = [
        'instant_snap capture.succeeded' => InstantSnapCaptureSucceededEvent::class,
        'instant_snap capture.failed' => InstantSnapCaptureFailedEvent::class,
        'auto_schedule capture.succeeded' => AutoScheduleCaptureSucceededEvent::class,
        'auto_schedule capture.failed' => AutoScheduleCaptureFailedEvent::class,
        'auto_schedule delivery.completed' => DeliveryCompletedEvent::class,
        'auto_schedule capture.changed' => CaptureChangedEvent::class,
        'auto_schedule schedule.paused' => SchedulePausedEvent::class,
        'auto_schedule sitemap_run.completed' => SitemapRunCompletedEvent::class,
    ];

    private function __construct() {}

    /**
     * Checks a delivery's signature and returns its typed event.
     *
     * @param string $payload The request body exactly as received, before any JSON parsing.
     * @param string $signatureHeader The X-WSW-Signature header.
     * @param string $secret The endpoint's signing secret from the dashboard.
     * @param int $toleranceSeconds How far the signature's timestamp may be from now, either way.
     * @param int|null $now Unix seconds to check against instead of the clock, for tests.
     *
     * @throws SignatureVerificationException When the header is malformed, too old or does not match.
     * @throws WebsiteScreenshotWorldException When a correctly signed body is not a webhook event.
     */
    public static function verify(
        string $payload,
        string $signatureHeader,
        #[\SensitiveParameter]
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?int $now = null,
    ): WebhookEvent {
        [$timestamp, $signatures] = self::parseHeader($signatureHeader);

        if (abs(($now ?? time()) - $timestamp) > $toleranceSeconds) {
            throw new SignatureVerificationException('The webhook signature timestamp is outside the tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $matched = false;
        foreach ($signatures as $signature) {
            $matched = hash_equals($expected, strtolower($signature)) || $matched;
        }
        if (!$matched) {
            throw new SignatureVerificationException('The webhook signature does not match the payload.');
        }

        return self::event($payload);
    }

    /**
     * @return array{int, list<string>}
     */
    private static function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', $part, 2);
            if (\count($pair) !== 2) {
                continue;
            }
            [$name, $value] = array_map('trim', $pair);
            if ($name === 't' && $timestamp === null) {
                $timestamp = $value;
            } elseif ($name === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || preg_match('/^\d+$/', $timestamp) !== 1) {
            throw new SignatureVerificationException('The webhook signature header has no valid "t" timestamp.');
        }
        if ($signatures === []) {
            throw new SignatureVerificationException('The webhook signature header has no "v1" signature.');
        }

        return [(int) $timestamp, $signatures];
    }

    private static function event(string $payload): WebhookEvent
    {
        try {
            $envelope = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WebsiteScreenshotWorldException('The webhook body is not JSON.', rawBody: $payload, previous: $e);
        }
        if (!\is_array($envelope) || !\is_string($envelope['type'] ?? null) || !\is_string($envelope['product'] ?? null)) {
            throw new WebsiteScreenshotWorldException('The webhook body is not an event.', rawBody: $payload);
        }

        $class = $envelope['type'] === 'ping'
            ? PingEvent::class
            : self::EVENTS[$envelope['product'] . ' ' . $envelope['type']] ?? UnknownEvent::class;

        return new $class($envelope);
    }
}
