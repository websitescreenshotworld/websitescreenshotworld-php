<?php

declare(strict_types=1);

namespace WebsiteScreenshotWorld\AutoSchedule;

use WebsiteScreenshotWorld\Exception\AuthenticationException;
use WebsiteScreenshotWorld\Exception\WebsiteScreenshotWorldException;
use WebsiteScreenshotWorld\Internal\HttpTransport;
use WebsiteScreenshotWorld\RequestOptions;

/**
 * Scheduled captures. Reach it through `$client->autoSchedule`.
 */
final class AutoScheduleService
{
    /**
     * @internal Use {@see \WebsiteScreenshotWorld\Client}.
     */
    public function __construct(
        private readonly HttpTransport $transport,
        #[\SensitiveParameter]
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly float $timeoutSeconds,
    ) {}

    /**
     * Captures a scheduled web page now. The result arrives by webhook.
     *
     * Safe to repeat: while a capture for the page is queued or running, the answer is `already_pending` and
     * nothing is charged.
     *
     * @param string $webPageId The web page's id from the dashboard; webhooks call it `schedule_id`.
     *
     * @throws WebsiteScreenshotWorldException
     */
    public function triggerNow(string $webPageId, ?RequestOptions $requestOptions = null): TriggerResult
    {
        $apiKey = $this->apiKey ?? throw new AuthenticationException(
            'No Auto Schedule API key. Pass autoScheduleApiKey to the Client or set WSW_AUTO_SCHEDULE_API_KEY.',
        );

        $response = $this->transport->send(
            'POST',
            $this->baseUrl . '/auto-schedule/v1/web-pages/' . rawurlencode($webPageId) . '/trigger-now',
            $requestOptions->timeoutSeconds ?? $this->timeoutSeconds,
            ['X-Api-Key' => $apiKey],
        );

        return new TriggerResult(HttpTransport::decode($response));
    }
}
