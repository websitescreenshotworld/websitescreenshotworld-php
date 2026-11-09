<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * A scheduled capture that differs from the one before it, as the capture.changed webhook carries it.
 */
final readonly class ChangedCapture extends ScheduledCapture
{
    /** How much of the page changed, in percent. */
    public float $changePercent;

    public ?string $comparedToCaptureId;

    /** Whether a change image is available. */
    public bool $changeImageAvailable;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->changePercent = Fields::float($data, 'change_percent');
        $this->comparedToCaptureId = Fields::nullableString($data, 'compared_to_capture_id');
        $this->changeImageAvailable = Fields::bool($data, 'change_image_available');
    }
}
