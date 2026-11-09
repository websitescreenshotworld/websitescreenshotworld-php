<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Webhook;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * A verified webhook delivery. {@see \WebsiteScreenshotWorld\Webhook::verify()} returns the subclass for its
 * type and product, or {@see UnknownEvent} for a type this version of the library does not know.
 */
abstract readonly class WebhookEvent
{
    /** The delivery id. */
    public string $id;

    /** An {@see EventType} value. */
    public string $type;

    public \DateTimeImmutable $createdAt;

    /** A {@see Product} value. */
    public string $product;

    /**
     * The whole envelope as received, including `data`.
     *
     * @var array<mixed>
     */
    public array $raw;

    /**
     * @param array<mixed> $envelope The decoded JSON body.
     */
    public function __construct(array $envelope)
    {
        $this->id = Fields::string($envelope, 'id');
        $this->type = Fields::string($envelope, 'type');
        $this->createdAt = Fields::dateTime($envelope, 'created_at');
        $this->product = Fields::string($envelope, 'product');
        $this->raw = $envelope;
    }
}
