<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

/**
 * Known values of a capture format. Plain strings are accepted too, so a newer format still works.
 */
final class Format
{
    public const PNG = 'png';
    public const JPEG = 'jpeg';
    public const WEBP = 'webp';
    public const TIFF = 'tiff';
    public const MP4 = 'mp4';
    public const WEBM = 'webm';
    public const MOV = 'mov';
    public const GIF = 'gif';
    public const IMAGE_PDF = 'image_pdf';
    public const DOCUMENT_PDF = 'document_pdf';

    private function __construct() {}
}
