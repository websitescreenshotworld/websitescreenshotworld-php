# Website Screenshot World for PHP

The PHP client for [Website Screenshot World](https://websitescreenshotworld.com): capture web pages on demand
with **Instant Snap**, start scheduled captures with **Auto Schedule**, and verify the webhooks both products
send.

It speaks PSR-18, so it uses the HTTP client your application already has (Guzzle, Symfony HttpClient, or any
other PSR-18 client).

## Installation

```bash
composer require websitescreenshotworld/websitescreenshotworld-php
```

If your project has no PSR-18 client yet, install one as well, for example `guzzlehttp/guzzle`. The library
finds it through [php-http/discovery](https://docs.php-http.org/en/latest/discovery.html); when Composer asks
whether to allow the `php-http/discovery` plugin, answer yes.

## Quick start

### Instant Snap

```php
use WebsiteScreenshotWorld\Client;
use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;
use WebsiteScreenshotWorld\InstantSnap\Format;
use WebsiteScreenshotWorld\InstantSnap\ScrollMode;
use WebsiteScreenshotWorld\InstantSnap\Viewport;

$client = new Client(instantSnapApiKey: 'wsw_api_...');

$capture = $client->instantSnap->capture(new CaptureOptions(
    url: 'https://example.com',
    format: Format::WEBP,
    viewport: new Viewport(width: 1440),
    scrollMode: ScrollMode::SCROLL,
));

echo $capture->id, ' ', $capture->status, "\n";
echo $capture->downloadUrl, "\n"; // valid for 60 minutes
```

Every option is a named constructor argument. An option you leave out (or pass as `null`) is not sent and takes
the API's default; `false`, `0` and `''` are sent as given. `$client->instantSnap->defaults()` lists the defaults
and limits, and `$client->instantSnap->catalog()` lists the devices and proxy countries you can use.

The Instant Snap methods are `capture()`, `captureFile()`, `captureToBucket()`, `submit()`, `submitToBucket()`,
`wait()`, `get()`, `download()`, `defaults()` and `catalog()`.

Tokens such as formats and statuses are plain strings with constants for the known values (`Format::PNG`,
`CaptureStatus::SUCCEEDED`, ...), so a value the API adds later still comes through.

### Auto Schedule

```php
use WebsiteScreenshotWorld\AutoSchedule\TriggerStatus;
use WebsiteScreenshotWorld\Client;

$client = new Client(autoScheduleApiKey: 'wsw_autoapi_...');

$result = $client->autoSchedule->triggerNow('7d4e2b1a-6c5f-4a3e-9d8c-2b1a0f9e8d7c');

if ($result->status === TriggerStatus::ALREADY_PENDING) {
    echo "A capture of this page is already queued; nothing new was started.\n";
}
```

The web page is created in the dashboard; its id is the `schedule_id` in webhooks. The capture's result arrives by
webhook.

## Saving the file directly

`captureFile()` answers with the file itself instead of a link. The API does not keep it, so read or save the
body once.

```php
use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;
use WebsiteScreenshotWorld\InstantSnap\Format;
use WebsiteScreenshotWorld\InstantSnap\MediaCategory;

$file = $client->instantSnap->captureFile(new CaptureOptions(
    url: 'https://example.com',
    mediaCategory: MediaCategory::PDF,
    format: Format::DOCUMENT_PDF,
));

$file->saveTo(__DIR__ . '/example.pdf');

echo $file->filename, ' ', $file->sha256, "\n";
```

`$file->body` is a PSR-7 stream if you would rather pass it on, for example to a response or to object storage.
The metadata from the `X-InstantSnap-*` headers is on the object: `id`, `status`, `width`, `height`,
`sizeBytes`, `durationMs`, `pageHttpStatus`, `capturedAt`, `finalUrl`, `sha256` and `partCount`.

## Uploading to your bucket

`captureToBucket()` uploads the file to an S3-compatible bucket and answers with the capture.

```php
use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;
use WebsiteScreenshotWorld\InstantSnap\S3DeliveryOptions;

$capture = $client->instantSnap->captureToBucket(new CaptureOptions(
    url: 'https://example.com',
    s3Delivery: new S3DeliveryOptions(
        accessKeyId: $awsAccessKeyId,
        secretAccessKey: $awsSecretAccessKey,
        bucket: 'captures',
        region: 'eu-west-2',
        keyPrefix: 'example/',
    ),
));

echo $capture->s3Delivery?->status, "\n"; // S3DeliveryStatus::IN_PROGRESS, ...
```

For other providers pass `endpointUrl` (https only), and `forcePathStyle: true` if they need it. Pass
`waitForSync: true` to have the API wait up to 30 seconds for the upload before answering.

## Async captures

`submit()` and `submitToBucket()` queue a capture and answer at once with it, `status` `queued`; it runs when a
browser is free (`queued`, then `running`, then `succeeded` or `failed`). `wait()` reads it every few seconds
until it has finished.

```php
use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;
use WebsiteScreenshotWorld\InstantSnap\CaptureStatus;
use WebsiteScreenshotWorld\InstantSnap\S3DeliveryOptions;

$queued = $client->instantSnap->submit(new CaptureOptions(
    url: 'https://example.com',
    timeout: 240, // browser time the capture may use, 30 to 300 seconds (120 when left out)
));

$capture = $client->instantSnap->wait($queued->id); // or wait($queued)
if ($capture->status === CaptureStatus::SUCCEEDED) {
    $client->instantSnap->download($capture, __DIR__ . '/example.png');
}

// The same, uploaded to your bucket:
$queued = $client->instantSnap->submitToBucket(new CaptureOptions(
    url: 'https://example.com',
    s3Delivery: new S3DeliveryOptions(accessKeyId: $awsAccessKeyId, secretAccessKey: $awsSecretAccessKey, bucket: 'captures'),
));
```

- `timeout` is for `submit()` and `submitToBucket()` only; the API refuses it on the other capture methods. The
  submit request itself uses `timeoutSeconds` (30 seconds), not the capture timeout, since nothing runs while it
  waits.
- `wait($capture, timeoutSeconds: 360, pollIntervalSeconds: 3)` calls `get()` until the status is no longer
  `queued`, `running` or `pending`. When `timeoutSeconds` runs out first it returns the capture as last read, still
  unfinished, rather than throwing, so you can wait again. A refusal from `get()` is thrown as it is.
- A capture still queued 15 minutes after it was accepted ends `failed` with the error code `capture_expired`,
  and is not charged.
- When your organization already has as many async captures waiting or running as it may, `submit()` throws a
  `RateLimitException` with the code `queue_full` and `getRetryAfterSeconds()`. It is not retried by the library;
  try again once some have finished.
- `callbackUrl` works here too, if you would rather be told by webhook than wait.

## Reading a capture back

`get()` reads a capture again and issues a fresh `downloadUrl`. `download()` fetches the file to a path or to a
stream.

```php
$capture = $client->instantSnap->get('3f6c2a1e-9b8d-4c7e-a5f4-1d2e3c4b5a69');

$client->instantSnap->download($capture, __DIR__ . '/capture.png');

// Or into a writable stream resource or PSR-7 stream you already have open:
$client->instantSnap->download($capture, $stream);
```

Files are deleted 24 hours after the capture (`$capture->expiresAt`).

## Errors and retries

Every error extends `WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException`:

| Exception | When |
|---|---|
| `AuthenticationException` | The key is missing, malformed, revoked or for the other product |
| `InvalidRequestException` | An option is out of range, or an Idempotency-Key was reused with a different body |
| `PlanLimitException` | No captures left, or the plan does not include the feature |
| `NotFoundException` | No such capture or web page for this account |
| `RateLimitException` | Too many requests, after the retries ran out, or the async queue is full (`queue_full`) |
| `CaptureException` | The website could not be captured or did not finish in time |
| `ServiceException` | The service is busy or unavailable, after the retries ran out |
| `ConnectionException` | No answer came back, after the retries ran out |
| `SignatureVerificationException` | A webhook signature did not verify |

```php
use WebsiteScreenshotWorld\Exception\CaptureException;
use WebsiteScreenshotWorld\Exception\PlanLimitException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;

try {
    $capture = $client->instantSnap->capture(new CaptureOptions(url: 'https://example.com'));
} catch (CaptureException $e) {
    // capture_failed, capture_timeout or payload_too_large
    echo "Could not capture: {$e->getMessage()}\n";
} catch (PlanLimitException $e) {
    echo "Plan limit: {$e->getErrorCode()}\n";
} catch (WebsiteScreenshotWorldException $e) {
    echo "Failed: {$e->getErrorCode()} (HTTP {$e->getHttpStatus()})\n";
}
```

Each exception carries `getErrorCode()`, `getMessage()`, `getHttpStatus()`, `getRetryAfterSeconds()` and
`getRawBody()`. Decide on the error code, never on the status or the message; a code this version of the library
does not know is thrown as the base class.

The client retries `rate_limited`, `capture_busy`, `service_unavailable` and `idempotency_key_in_use` answers, and
requests that got no answer at all, up to twice (`maxRetries`). It waits as long as the API asks
(`retry_after_seconds` or `Retry-After`, at most 60 seconds), otherwise 0.5 seconds and then 1 second with a
little jitter. Refusals such as `capture_failed` and `queue_full` are not retried.

## Idempotency keys

Every capture carries an `Idempotency-Key`, and retries reuse it, so a retry never captures twice. The library
makes a random one per call; pass your own to make a call safe to repeat across processes. For 24 hours, a second
request with the same key returns the first capture instead of capturing again.

```php
use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;
use WebsiteScreenshotWorld\RequestOptions;

$capture = $client->instantSnap->capture(
    new CaptureOptions(url: 'https://example.com'),
    new RequestOptions(idempotencyKey: "order-{$orderId}-hero"),
);
```

## Timeouts

A capture can run for up to 120 seconds, so a capture call may take 180 seconds and every other call 30 seconds.
The time covers the whole call, retries and the waits between them included: a retry whose wait would run past it
is not started, and a call that runs out of time throws `ConnectionException` without retrying.

PSR-18 has no per-request timeout, so the time of each request belongs to the HTTP client:

- When you do not pass `httpClient`, the library discovers one. If it is Guzzle or Symfony HttpClient (6.2 or
  later), the library configures it with these timeouts, and gives a retry only the time the call has left.
  Change them with `captureTimeoutSeconds` and `timeoutSeconds`, or for one call with
  `new RequestOptions(timeoutSeconds: ...)`.
- When you pass your own client, it keeps its own timeouts, and the library only stops retrying once the call's
  time has run out. Give it a timeout above 120 seconds, or captures can be cut off while they are still running:

```php
use GuzzleHttp\Client as GuzzleClient;
use WebsiteScreenshotWorld\Client;

$client = new Client(httpClient: new GuzzleClient(['timeout' => 180]));
```

## Redirects

The library never follows a redirect: it would carry the API key to wherever the redirect pointed. Neither the API
nor a download link redirects, so a `3xx` answer is thrown as an error. A discovered Guzzle or Symfony HttpClient is
set up that way. If you pass your own client, make sure it does not follow redirects either. Guzzle's PSR-18
`sendRequest()` never does; for Symfony HttpClient, set `max_redirects` to `0`:

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use WebsiteScreenshotWorld\Client;

$client = new Client(httpClient: new Psr18Client(HttpClient::create(['timeout' => 180, 'max_redirects' => 0])));
```

## Webhooks

Deliveries are signed with the `X-WSW-Signature` header. `Webhook::verify()` checks the signature against the
endpoint's secret, refuses deliveries more than five minutes old, and returns a typed event.

Pass the body **exactly as received**. If a framework parses the JSON and you serialise it again, the bytes change
and the signature no longer matches.

### Laravel

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use WebsiteScreenshotWorld\Exception\SignatureVerificationException;
use WebsiteScreenshotWorld\Webhook;

Route::post('/webhooks/website-screenshot-world', function (Request $request) {
    try {
        $event = Webhook::verify(
            $request->getContent(),
            $request->header('X-WSW-Signature', ''),
            config('services.website_screenshot_world.webhook_secret'),
        );
    } catch (SignatureVerificationException) {
        abort(400);
    }

    // Handle $event.

    return response()->noContent();
});
```

Webhooks carry no CSRF token: register the route in `routes/api.php`, or exclude its path from CSRF verification.

### Symfony

```php
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WebsiteScreenshotWorld\Exception\SignatureVerificationException;
use WebsiteScreenshotWorld\Webhook;

final class WebsiteScreenshotWorldWebhookController
{
    public function __construct(
        #[Autowire(env: 'WSW_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
    ) {}

    #[Route('/webhooks/website-screenshot-world', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $event = Webhook::verify(
                $request->getContent(),
                $request->headers->get('X-WSW-Signature', ''),
                $this->webhookSecret,
            );
        } catch (SignatureVerificationException) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        // Handle $event.

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
```

### Plain PHP

```php
use WebsiteScreenshotWorld\Exception\SignatureVerificationException;
use WebsiteScreenshotWorld\Webhook;

try {
    $event = Webhook::verify(
        (string) file_get_contents('php://input'),
        $_SERVER['HTTP_X_WSW_SIGNATURE'] ?? '',
        (string) getenv('WSW_WEBHOOK_SECRET'),
    );
} catch (SignatureVerificationException) {
    http_response_code(400);
    exit;
}

http_response_code(204);
```

### Handling events

Each event type has its own class with a typed `data` property; all of them extend `WebhookEvent`, which carries
`id`, `type`, `createdAt`, `product` and the `raw` envelope.

```php
use WebsiteScreenshotWorld\Webhook\CaptureChangedEvent;
use WebsiteScreenshotWorld\Webhook\InstantSnapCaptureFailedEvent;
use WebsiteScreenshotWorld\Webhook\InstantSnapCaptureSucceededEvent;
use WebsiteScreenshotWorld\Webhook\SchedulePausedEvent;
use WebsiteScreenshotWorld\Webhook\UnknownEvent;

switch (true) {
    case $event instanceof InstantSnapCaptureSucceededEvent:
        // $event->data is a Capture; get() it for a download URL.
        $capture = $client->instantSnap->get($event->data->id);
        break;
    case $event instanceof InstantSnapCaptureFailedEvent:
        error_log("Capture {$event->data->id} failed: {$event->data->errorCode}");
        break;
    case $event instanceof CaptureChangedEvent:
        echo "{$event->data->scheduleName} changed by {$event->data->changePercent} %\n";
        break;
    case $event instanceof SchedulePausedEvent:
        echo "{$event->data->scheduleName} was paused: {$event->data->reason}\n";
        break;
    case $event instanceof UnknownEvent:
        // A type this version of the library does not know yet; $event->type and $event->data are still there.
        break;
}
```

| Class | Type | Product | `data` |
|---|---|---|---|
| `InstantSnapCaptureSucceededEvent`, `InstantSnapCaptureFailedEvent` | `capture.succeeded`, `capture.failed` | Instant Snap | `InstantSnap\Capture` |
| `AutoScheduleCaptureSucceededEvent`, `AutoScheduleCaptureFailedEvent` | `capture.succeeded`, `capture.failed` | Auto Schedule | `AutoSchedule\ScheduledCapture` |
| `DeliveryCompletedEvent` | `delivery.completed` | Auto Schedule | `AutoSchedule\DeliveredCapture` |
| `CaptureChangedEvent` | `capture.changed` | Auto Schedule | `AutoSchedule\ChangedCapture` |
| `SchedulePausedEvent` | `schedule.paused` | Auto Schedule | `AutoSchedule\PausedSchedule` |
| `SitemapRunCompletedEvent` | `sitemap_run.completed` | Auto Schedule | `AutoSchedule\SitemapRun` |
| `PingEvent` | `ping` | both | `Webhook\Ping` |
| `UnknownEvent` | anything else | either | the raw `data` array |

`verify()` takes a fourth argument, `toleranceSeconds` (300 by default), and a fifth, `now`, to check against a
fixed Unix time in tests.

## Configuration

| Argument | Default |
|---|---|
| `instantSnapApiKey` | The `WSW_INSTANT_SNAP_API_KEY` environment variable |
| `autoScheduleApiKey` | The `WSW_AUTO_SCHEDULE_API_KEY` environment variable |
| `instantSnapBaseUrl` | `https://api.websitescreenshotworld.com` |
| `autoScheduleBaseUrl` | `https://autoapi.websitescreenshotworld.com` |
| `captureTimeoutSeconds` | `180` |
| `timeoutSeconds` | `30` |
| `maxRetries` | `2` |
| `httpClient` | A discovered PSR-18 client |
| `requestFactory`, `streamFactory` | Discovered PSR-17 factories |

Either key may be left out. Calling the product whose key is missing throws an `AuthenticationException` before
anything is sent. `defaults()` needs no key.

Results are readonly objects with `DateTimeImmutable` dates in UTC. Each has a `raw` array with the answer as
received, including fields this version of the library does not know yet.

## Requirements

- PHP 8.2 or later (tested on 8.2 and 8.4)
- A PSR-18 HTTP client and PSR-17 factories, such as Guzzle 7 or Symfony HttpClient

## Development

The tests play the shared cases in [`spec/`](https://github.com/websitescreenshotworld/websitescreenshotworld-spec) against its small Node.js server, so they need Node.js 22
or later. `spec/` is the [websitescreenshotworld-spec](https://github.com/websitescreenshotworld/websitescreenshotworld-spec) submodule: clone with `git clone --recurse-submodules`, or run `git submodule update --init` in an existing clone.

```bash
composer install
composer test
composer lint
```

## License

MIT. See [LICENSE](https://github.com/websitescreenshotworld/websitescreenshotworld-php/blob/main/LICENSE).
