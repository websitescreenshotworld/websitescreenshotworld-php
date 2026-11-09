<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Internal;

/**
 * Builds a JSON object from option fields, leaving out the ones that were not set.
 *
 * @internal
 */
final class Wire
{
    private function __construct() {}

    /**
     * Null means "not set" and is dropped; `false`, `0` and `""` are values and are kept. Always a JSON object,
     * even when empty, so a block such as `timestamp_watermark: {}` still turns its feature on.
     *
     * @param array<string, mixed> $fields
     */
    public static function object(array $fields): \stdClass
    {
        return (object) array_filter($fields, static fn(mixed $value): bool => $value !== null);
    }

    /**
     * @param array<string, string>|null $map
     */
    public static function map(?array $map): ?\stdClass
    {
        return $map === null ? null : (object) $map;
    }
}
