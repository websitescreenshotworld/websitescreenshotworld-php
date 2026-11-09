<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebsiteScreenshotWorld\AutoSchedule\DeliveredCapture;
use WebsiteScreenshotWorld\Exception\SignatureVerificationException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Tests\Support\Json;
use WebsiteScreenshotWorld\Webhook;
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

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private const EVENT_CLASSES = [
        'auto-schedule.capture.changed.json' => CaptureChangedEvent::class,
        'auto-schedule.capture.failed.json' => AutoScheduleCaptureFailedEvent::class,
        'auto-schedule.capture.succeeded.json' => AutoScheduleCaptureSucceededEvent::class,
        'auto-schedule.delivery.completed.json' => DeliveryCompletedEvent::class,
        'auto-schedule.ping.json' => PingEvent::class,
        'auto-schedule.schedule.paused.json' => SchedulePausedEvent::class,
        'auto-schedule.sitemap_run.completed.json' => SitemapRunCompletedEvent::class,
        'instant-snap.capture.failed.json' => InstantSnapCaptureFailedEvent::class,
        'instant-snap.capture.succeeded.json' => InstantSnapCaptureSucceededEvent::class,
        'unknown.json' => UnknownEvent::class,
    ];

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function vectors(): iterable
    {
        $vectors = Json::file(Json::SPEC_DIR . '/webhook-vectors.json');
        foreach (Json::list($vectors['cases']) as $case) {
            $case = Json::object($case);
            yield Json::string($case['name']) => [Json::string($vectors['secret']), $case];
        }
    }

    /**
     * @param array<string, mixed> $case
     */
    #[DataProvider('vectors')]
    public function testSignatureVector(string $secret, array $case): void
    {
        $verify = static fn(): WebhookEvent => Webhook::verify(
            Json::string($case['body']),
            Json::string($case['header']),
            $secret,
            \is_int($case['tolerance'] ?? null) ? $case['tolerance'] : Webhook::DEFAULT_TOLERANCE_SECONDS,
            \is_int($case['now']) ? $case['now'] : throw new \UnexpectedValueException('"now" must be an integer.'),
        );

        if ($case['valid'] === true) {
            self::assertInstanceOf(PingEvent::class, $verify());
        } else {
            $this->expectException(SignatureVerificationException::class);
            $verify();
        }
    }

    /**
     * @return iterable<string, array{string, class-string<WebhookEvent>}>
     */
    public static function events(): iterable
    {
        foreach (Json::specFiles('events') as $path) {
            yield basename($path) => [$path, self::EVENT_CLASSES[basename($path)]];
        }
    }

    /**
     * @param class-string<WebhookEvent> $class
     */
    #[DataProvider('events')]
    public function testEventExampleParsesToItsType(string $path, string $class): void
    {
        $payload = (string) file_get_contents($path);
        $envelope = Json::file($path);

        $event = Webhook::verify($payload, self::sign($payload, 1790418600), self::SECRET, now: 1790418600);

        self::assertInstanceOf($class, $event);
        self::assertSame($envelope['id'], $event->id);
        self::assertSame($envelope['type'], $event->type);
        self::assertSame($envelope['product'], $event->product);
        self::assertSame('2026-09-26T10:30:05.000+00:00', $event->createdAt->format('Y-m-d\TH:i:s.vP'));
        self::assertSame($envelope, $event->raw);
    }

    public function testEveryEventExampleHasAnExpectedType(): void
    {
        self::assertSame(array_keys(self::EVENT_CLASSES), array_map(basename(...), Json::specFiles('events')));
    }

    public function testTypedEventDataIsReadable(): void
    {
        $payload = (string) file_get_contents(Json::SPEC_DIR . '/events/auto-schedule.delivery.completed.json');

        $event = Webhook::verify($payload, self::sign($payload, 1790418600), self::SECRET, now: 1790418600);

        self::assertInstanceOf(DeliveryCompletedEvent::class, $event);
        self::assertInstanceOf(DeliveredCapture::class, $event->data);
        self::assertSame('7d4e2b1a-6c5f-4a3e-9d8c-2b1a0f9e8d7c', $event->data->scheduleId);
        self::assertSame('html', $event->data->pageSource?->format);
        self::assertCount(1, $event->data->destinations);
        self::assertSame('aws_s3', $event->data->destinations[0]->provider);
        self::assertSame('2026-09-26 10:30:09', $event->data->destinations[0]->completedAt?->format('Y-m-d H:i:s'));
    }

    public function testUnknownEventKeepsItsTypeAndData(): void
    {
        $payload = (string) file_get_contents(Json::SPEC_DIR . '/events/unknown.json');

        $event = Webhook::verify($payload, self::sign($payload, 1790418600), self::SECRET, now: 1790418600);

        self::assertInstanceOf(UnknownEvent::class, $event);
        self::assertSame('capture.teleported', $event->type);
        self::assertSame(['anything' => 1], $event->data);
    }

    public function testSignedBodyThatIsNotJsonIsRefused(): void
    {
        $this->expectException(WebsiteScreenshotWorldException::class);
        $this->expectExceptionMessage('not JSON');

        Webhook::verify('not json', self::sign('not json', 1790418600), self::SECRET, now: 1790418600);
    }

    public function testClockDefaultsToNow(): void
    {
        $payload = (string) file_get_contents(Json::SPEC_DIR . '/events/auto-schedule.ping.json');

        self::assertInstanceOf(PingEvent::class, Webhook::verify($payload, self::sign($payload, time()), self::SECRET));
    }

    private static function sign(string $payload, int $timestamp): string
    {
        return "t={$timestamp},v1=" . hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET);
    }
}
