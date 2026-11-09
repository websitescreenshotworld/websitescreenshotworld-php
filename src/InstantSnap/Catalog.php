<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\InstantSnap;

use WebsiteScreenshotWorld\Internal\Fields;

/**
 * What `emulatedDevice` and `Proxy::$country` accept.
 */
final readonly class Catalog
{
    /** @var list<string> */
    public array $emulatedDevices;

    /** @var list<string> */
    public array $proxyCountries;

    /** @var array<mixed> */
    public array $raw;

    /**
     * @param array<mixed> $data The decoded JSON object, in the API's field names.
     */
    public function __construct(array $data)
    {
        $this->emulatedDevices = Fields::stringList($data, 'emulated_devices');
        $this->proxyCountries = Fields::stringList($data, 'proxy_countries');
        $this->raw = $data;
    }
}
