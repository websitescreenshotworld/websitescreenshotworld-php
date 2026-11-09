<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * A scheduled capture and where it was delivered, as the delivery.completed webhook carries it.
 */
final readonly class DeliveredCapture extends ScheduledCapture
{
    /** @var list<Destination> */
    public array $destinations;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->destinations = array_map(
            static fn(array $destination): Destination => new Destination($destination),
            Fields::objectList($data, 'destinations'),
        );
    }
}
