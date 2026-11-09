<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Internal\Fields;
use WebsiteScreenshotWorld\Internal\Streams;

/**
 * A file returned by {@see InstantSnapService::captureFile()}, with the metadata from its `X-InstantSnap-*`
 * headers. The file is not kept by the API, so read or save the body once.
 */
final readonly class CaptureFile
{
    /** The file, as a stream that has not been read yet. */
    public StreamInterface $body;

    /** The file name from Content-Disposition, as the server sent it. */
    public ?string $filename;

    public ?string $contentType;

    public string $id;

    /** A {@see CaptureStatus} value, `delivered` for a returned file. */
    public string $status;

    public ?int $width;

    public ?int $height;

    public ?int $sizeBytes;

    public ?int $durationMs;

    public ?int $pageHttpStatus;

    public ?string $specVersion;

    public ?\DateTimeImmutable $capturedAt;

    /** Absent when the URL is not plain ASCII. */
    public ?string $finalUrl;

    /** Lowercase hex SHA-256 of the body. */
    public ?string $sha256;

    /** Set only when the answer has more than one part. */
    public ?int $partCount;

    /**
     * All response headers, as PSR-7 returns them.
     *
     * @var array<string, list<string>>
     */
    public array $headers;

    public function __construct(ResponseInterface $response)
    {
        $header = static function (string $name) use ($response): ?string {
            $value = $response->getHeaderLine($name);

            return $value === '' ? null : $value;
        };
        $number = static function (string $name) use ($header): ?int {
            $value = $header($name);

            return $value !== null && ctype_digit($value) ? (int) $value : null;
        };

        $this->body = $response->getBody();
        $this->filename = self::filename($response->getHeaderLine('Content-Disposition'));
        $this->contentType = $header('Content-Type');
        $this->id = $header('X-InstantSnap-Id') ?? throw self::missing('X-InstantSnap-Id', $response);
        $this->status = $header('X-InstantSnap-Status') ?? throw self::missing('X-InstantSnap-Status', $response);
        $this->width = $number('X-InstantSnap-Width');
        $this->height = $number('X-InstantSnap-Height');
        $this->sizeBytes = $number('X-InstantSnap-Size-Bytes');
        $this->durationMs = $number('X-InstantSnap-Duration-Ms');
        $this->pageHttpStatus = $number('X-InstantSnap-Page-Http-Status');
        $this->specVersion = $header('X-InstantSnap-Spec-Version');
        $capturedAt = $header('X-InstantSnap-Captured-At');
        $this->capturedAt = $capturedAt === null ? null : Fields::parseDateTime($capturedAt);
        $this->finalUrl = $header('X-InstantSnap-Final-Url');
        $this->sha256 = $header('X-InstantSnap-Sha256');
        $this->partCount = $number('X-InstantSnap-Part-Count');
        /** @var array<string, list<string>> $headers */
        $headers = $response->getHeaders();
        $this->headers = $headers;
    }

    /**
     * Writes the file to a path, replacing any file there.
     *
     * @return int The number of bytes written.
     *
     * @throws \RuntimeException When the file cannot be written.
     */
    public function saveTo(string $path): int
    {
        return Streams::copy($this->body, $path);
    }

    private static function filename(string $disposition): ?string
    {
        if (preg_match("/filename\\*\\s*=\\s*[^']*'[^']*'([^;\\s]+)/i", $disposition, $match) === 1) {
            return rawurldecode($match[1]);
        }
        if (preg_match('/filename\s*=\s*"((?:[^"\\\\]|\\\\.)*)"/i', $disposition, $match) === 1) {
            return stripslashes($match[1]);
        }
        if (preg_match('/filename\s*=\s*([^;\s]+)/i', $disposition, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    private static function missing(string $header, ResponseInterface $response): WebsiteScreenshotWorldException
    {
        return new WebsiteScreenshotWorldException(
            \sprintf('The API returned a file without the %s header.', $header),
            httpStatus: $response->getStatusCode(),
        );
    }
}
