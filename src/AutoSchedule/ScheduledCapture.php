<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * A scheduled capture, as the Auto Schedule capture.succeeded and capture.failed webhooks carry it.
 */
readonly class ScheduledCapture
{
    public string $id;

    /** The web page's id, which {@see AutoScheduleService::triggerNow()} calls `webPageId`. */
    public string $scheduleId;

    public string $scheduleName;

    public string $url;

    public string $status;

    public string $mediaCategory;

    public ?string $format;

    /** The status the website answered with. */
    public ?int $pageHttpStatus;

    public ?\DateTimeImmutable $capturedAt;

    /** Where the browser ended up, when the page redirected. */
    public ?string $finalUrl;

    /** Lowercase hex SHA-256 of the file. */
    public ?string $mediaSha256;

    public ?PageSource $pageSource;

    /** Set when the capture is part of a sitemap run. */
    public ?string $sitemapRunId;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->id = Fields::string($data, 'id');
        $this->scheduleId = Fields::string($data, 'schedule_id');
        $this->scheduleName = Fields::string($data, 'schedule_name');
        $this->url = Fields::string($data, 'url');
        $this->status = Fields::string($data, 'status');
        $this->mediaCategory = Fields::string($data, 'media_category');
        $this->format = Fields::nullableString($data, 'format');
        $this->pageHttpStatus = Fields::nullableInt($data, 'page_http_status');
        $this->capturedAt = Fields::nullableDateTime($data, 'captured_at');
        $this->finalUrl = Fields::nullableString($data, 'final_url');
        $this->mediaSha256 = Fields::nullableString($data, 'media_sha256');
        $pageSource = Fields::nullableObject($data, 'page_source');
        $this->pageSource = $pageSource === null ? null : new PageSource($pageSource);
        $this->sitemapRunId = Fields::nullableString($data, 'sitemap_run_id');
        $this->raw = $data;
    }
}
