<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests\Support;

/**
 * Typed access to the shared JSON files in spec/.
 */
final class Json
{
    public const SPEC_DIR = __DIR__ . '/../../spec';

    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function file(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Could not read {$path}.");
        }

        return self::object(json_decode($contents, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public static function object(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \UnexpectedValueException('Expected a JSON object.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new \UnexpectedValueException('Expected a JSON array.');
        }

        return $value;
    }

    public static function string(mixed $value): string
    {
        return \is_string($value) ? $value : throw new \UnexpectedValueException('Expected a JSON string.');
    }

    /**
     * Sorts object keys and turns whole floats into integers, so two decoded documents compare as values.
     */
    public static function canonical(mixed $value): mixed
    {
        if (\is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (!\is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);
        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @return list<string> The paths of every file in a spec/ folder, sorted.
     */
    public static function specFiles(string $folder): array
    {
        $files = glob(self::SPEC_DIR . "/{$folder}/*.json");

        return $files === false ? [] : $files;
    }
}
