<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Wire;

/**
 * Elements to act on before capturing.
 */
final readonly class Selectors implements \JsonSerializable
{
    /**
     * @param string|null $xpath Capture only the element this XPath selects. At most 512 characters.
     * @param string|null $hide Up to 25 comma-separated CSS selectors (an XPath starting with `//` works too) of
     *                          elements to hide, at most 2000 characters. Each is applied on its own; one the
     *                          browser rejects is skipped.
     * @param string|null $click CSS selector of an element to click. At most 512 characters.
     * @param string|null $hover CSS selector of an element to hover. At most 512 characters.
     */
    public function __construct(
        public ?string $xpath = null,
        public ?string $hide = null,
        public ?string $click = null,
        public ?string $hover = null,
    ) {}

    public function jsonSerialize(): \stdClass
    {
        return Wire::object([
            'xpath' => $this->xpath,
            'hide' => $this->hide,
            'click' => $this->click,
            'hover' => $this->hover,
        ]);
    }
}
