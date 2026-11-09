<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;

final class NetworkFailure extends \RuntimeException implements ClientExceptionInterface {}
