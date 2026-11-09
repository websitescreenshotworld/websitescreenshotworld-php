<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Internal;

use Psr\Http\Message\StreamInterface;

/**
 * Copies a response body to a file path, a PHP stream resource or a PSR-7 stream, in chunks.
 *
 * @internal
 */
final class Streams
{
    private const CHUNK_BYTES = 65536;

    private function __construct() {}

    /**
     * @param string|resource|StreamInterface $target
     *
     * @return int The number of bytes written.
     */
    public static function copy(StreamInterface $source, mixed $target): int
    {
        self::assertTarget($target);

        if (\is_string($target)) {
            return self::toPath($source, $target);
        }

        if ($target instanceof StreamInterface) {
            return self::pump($source, static fn(string $chunk): int => $target->write($chunk));
        }

        return self::pump($source, static fn(string $chunk): int|false => fwrite($target, $chunk));
    }

    public static function assertTarget(mixed $target): void
    {
        if (!\is_string($target) && !\is_resource($target) && !$target instanceof StreamInterface) {
            throw new \InvalidArgumentException('The target must be a file path, a stream resource or a PSR-7 stream.');
        }
    }

    private static function toPath(StreamInterface $source, string $path): int
    {
        $handle = @fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException(\sprintf('Could not open "%s" for writing: %s', $path, error_get_last()['message'] ?? 'unknown error'));
        }

        try {
            return self::pump($source, static fn(string $chunk): int|false => fwrite($handle, $chunk));
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param \Closure(string): (int|false) $write
     */
    private static function pump(StreamInterface $source, \Closure $write): int
    {
        if ($source->isSeekable()) {
            $source->rewind();
        }

        $total = 0;
        while (!$source->eof()) {
            $chunk = $source->read(self::CHUNK_BYTES);
            if ($chunk === '') {
                break;
            }
            $total += \strlen($chunk);
            while ($chunk !== '') {
                $written = $write($chunk);
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('Could not write the file to the target.');
                }
                $chunk = substr($chunk, $written);
            }
        }

        return $total;
    }
}
