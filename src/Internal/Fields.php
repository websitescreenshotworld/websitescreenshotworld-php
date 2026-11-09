<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Internal;

use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;

/**
 * Typed reads from a decoded JSON object. A missing nullable field reads as null; unknown fields are ignored.
 *
 * @internal
 */
final class Fields
{
    private const DATE_TIME = '/^(\d{4}-\d{2}-\d{2})[Tt ](\d{2}:\d{2}:\d{2})(?:\.(\d+))?([Zz]|[+-]\d{2}:?\d{2})$/';

    private function __construct() {}

    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        return self::nullableString($data, $key) ?? throw self::unexpected($key, 'a string');
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw self::unexpected($key, 'a string');
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        return self::nullableInt($data, $key) ?? throw self::unexpected($key, 'an integer');
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_int($value)) {
            throw self::unexpected($key, 'an integer');
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function float(array $data, string $key): float
    {
        return self::nullableFloat($data, $key) ?? throw self::unexpected($key, 'a number');
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_int($value) && !\is_float($value)) {
            throw self::unexpected($key, 'a number');
        }

        return $value === null ? null : (float) $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        return self::nullableBool($data, $key) ?? throw self::unexpected($key, 'a boolean');
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableBool(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_bool($value)) {
            throw self::unexpected($key, 'a boolean');
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function dateTime(array $data, string $key): \DateTimeImmutable
    {
        return self::nullableDateTime($data, $key) ?? throw self::unexpected($key, 'a date-time');
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableDateTime(array $data, string $key): ?\DateTimeImmutable
    {
        $value = self::nullableString($data, $key);

        return $value === null ? null : (self::parseDateTime($value) ?? throw self::unexpected($key, 'a date-time'));
    }

    /**
     * Parses an ISO 8601 date-time with any fraction length and `Z` or an offset, and returns it in UTC.
     */
    public static function parseDateTime(string $value): ?\DateTimeImmutable
    {
        if (preg_match(self::DATE_TIME, $value, $parts) !== 1) {
            return null;
        }

        $fraction = substr(str_pad($parts[3], 6, '0'), 0, 6);
        $offset = strtoupper($parts[4]) === 'Z' ? '+00:00' : $parts[4];
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.uP', "{$parts[1]} {$parts[2]}.{$fraction}{$offset}");

        return $parsed === false ? null : $parsed->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    public static function nullableObject(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_array($value)) {
            throw self::unexpected($key, 'an object');
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function object(array $data, string $key): array
    {
        return self::nullableObject($data, $key) ?? throw self::unexpected($key, 'an object');
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    public static function objectList(array $data, string $key): array
    {
        $list = [];
        foreach (self::object($data, $key) as $item) {
            $list[] = \is_array($item) ? $item : throw self::unexpected($key, 'a list of objects');
        }

        return $list;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>|null
     */
    public static function nullableStringList(array $data, string $key): ?array
    {
        $value = self::nullableObject($data, $key);
        if ($value === null) {
            return null;
        }

        $list = [];
        foreach ($value as $item) {
            $list[] = \is_string($item) ? $item : throw self::unexpected($key, 'a list of strings');
        }

        return $list;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function stringList(array $data, string $key): array
    {
        return self::nullableStringList($data, $key) ?? throw self::unexpected($key, 'a list of strings');
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, string>|null
     */
    public static function nullableStringMap(array $data, string $key): ?array
    {
        $value = self::nullableObject($data, $key);
        if ($value === null) {
            return null;
        }

        $map = [];
        foreach ($value as $name => $item) {
            $map[(string) $name] = \is_string($item) ? $item : throw self::unexpected($key, 'a map of strings');
        }

        return $map;
    }

    private static function unexpected(string $key, string $expected): WebsiteScreenshotWorldException
    {
        return new WebsiteScreenshotWorldException(\sprintf('The answer\'s "%s" is missing or is not %s.', $key, $expected));
    }
}
