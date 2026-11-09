# Changelog

All notable changes to this library are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this library adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 - 2026-11-09

### Added

- `Client` for the Instant Snap and Auto Schedule APIs, working with any PSR-18 HTTP client and reading its keys
  from `WSW_INSTANT_SNAP_API_KEY` and `WSW_AUTO_SCHEDULE_API_KEY` when they are not passed in.
- Instant Snap: `capture()`, `captureFile()`, `captureToBucket()`, `get()`, `download()`, `defaults()` and
  `catalog()`, with readonly option and result classes and constants for every known token.
- Auto Schedule: `triggerNow()`.
- The `pressEscape` capture option, and the `sitemap_schedule_not_triggerable` error code (an
  `InvalidRequestException`: a sitemap schedule runs only on its schedule), from API version 1.1.0.
- An Idempotency-Key on every capture, random or your own, reused on retries.
- Retries for rate limits, busy capture slots, unavailable services and connection failures, following the
  API's `retry_after_seconds` hint.
- Timeouts of 180 seconds for captures and 30 seconds for other calls, covering the whole call with its retries,
  applied to a discovered Guzzle or Symfony HttpClient; a retry gets only the time the call has left, and a call
  that runs out of time is not retried.
- Redirects are never followed by a discovered Guzzle or Symfony HttpClient, so the API key is never sent to
  another host; a 3xx answer is thrown as an error.
- `Webhook::verify()`, which checks the `X-WSW-Signature` header and returns a typed event for each event type of
  both products, or `UnknownEvent`.
- One exception class per error kind, all extending `WebsiteScreenshotWorldException`.
- Async Instant Snap captures: `submit()`, `submitToBucket()` and `wait()`, the `timeout` capture option
  (30-300 seconds, async only), the `CaptureStatus::QUEUED` and `CaptureStatus::RUNNING` statuses, and the
  `queue_full` (a `RateLimitException`, not retried) and `capture_expired` (a `CaptureException`) error codes.
  `Capture::$mediaCategory` is nullable, since a queued capture has none yet.
