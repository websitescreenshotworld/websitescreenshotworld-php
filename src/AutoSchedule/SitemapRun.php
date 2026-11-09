<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * A finished sitemap run, as the sitemap_run.completed webhook carries it.
 */
final readonly class SitemapRun
{
    public string $sitemapRunId;

    public string $scheduleId;

    public string $scheduleName;

    public string $sitemapUrl;

    public string $status;

    public ?\DateTimeImmutable $runAt;

    public ?\DateTimeImmutable $completedAt;

    public int $pagesListed;

    public int $pagesMatched;

    public int $pagesQueued;

    public int $pagesSucceeded;

    public int $pagesFailed;

    public int $pagesOverLimit;

    public int $pagesOverQuota;

    public ?string $message;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->sitemapRunId = Fields::string($data, 'sitemap_run_id');
        $this->scheduleId = Fields::string($data, 'schedule_id');
        $this->scheduleName = Fields::string($data, 'schedule_name');
        $this->sitemapUrl = Fields::string($data, 'sitemap_url');
        $this->status = Fields::string($data, 'status');
        $this->runAt = Fields::nullableDateTime($data, 'run_at');
        $this->completedAt = Fields::nullableDateTime($data, 'completed_at');
        $this->pagesListed = Fields::int($data, 'pages_listed');
        $this->pagesMatched = Fields::int($data, 'pages_matched');
        $this->pagesQueued = Fields::int($data, 'pages_queued');
        $this->pagesSucceeded = Fields::int($data, 'pages_succeeded');
        $this->pagesFailed = Fields::int($data, 'pages_failed');
        $this->pagesOverLimit = Fields::int($data, 'pages_over_limit');
        $this->pagesOverQuota = Fields::int($data, 'pages_over_quota');
        $this->message = Fields::nullableString($data, 'message');
        $this->raw = $data;
    }
}
