<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * An event whose type this version of the library does not know yet. Its signature was still verified.
 */
final readonly class UnknownEvent extends WebhookEvent
{
    /** @var array<mixed> */
    public array $data;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        parent::__construct($envelope);
        $this->data = Fields::nullableObject($envelope, 'data') ?? [];
    }
}
