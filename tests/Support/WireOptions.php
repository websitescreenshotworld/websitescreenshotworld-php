<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests\Support;

use WebsiteScreenshotWorld\InstantSnap\CaptureOptions;

/**
 * Reads a fixture's wire-form options into the typed option objects, the way a caller would write them.
 */
final class WireOptions
{
    private function __construct() {}

    /**
     * @param array<string, mixed> $wire
     */
    public static function captureOptions(array $wire): CaptureOptions
    {
        $options = self::build(CaptureOptions::class, $wire);
        \assert($options instanceof CaptureOptions);

        return $options;
    }

    /**
     * Maps each snake_case field to the camelCase constructor argument, building nested blocks from their
     * declared class. Maps such as `headers` stay arrays.
     *
     * @param class-string $class
     * @param array<string, mixed> $wire
     */
    private static function build(string $class, array $wire): object
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            throw new \LogicException("{$class} has no constructor.");
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        $arguments = [];
        foreach ($wire as $field => $value) {
            $name = lcfirst(str_replace('_', '', ucwords($field, '_')));
            $type = ($parameters[$name] ?? throw new \LogicException("{$class} has no option for \"{$field}\"."))->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string $nested */
                $nested = $type->getName();
                $value = self::build($nested, Json::object($value));
            }
            $arguments[$name] = $value;
        }

        return new $class(...$arguments);
    }
}
