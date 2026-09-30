<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Model\Api\Event;

use Tiktok\Tiktok\Model\Api\AbstractApiRequest;

class EventSender extends AbstractApiRequest
{
    /**
     * Endpoint
     */
    public const ENDPOINT = '/open_api/v1.3/event/track/';

    /**
     * Event Source
     */
    public const EVENT_SOURCE = 'web';

    /**
     * Send events
     *
     * @param array $data
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     */
    public function sendEvents(array $data): array
    {
        $data = [
            'event_source' => self::EVENT_SOURCE,
            'event_source_id' => $this->scopeManager->getPixelCode(),
            'partner_name' => 'Magento',
            'data' => $data
        ];
        return $this->sendRequest('POST', $data, self::ENDPOINT);
    }
}
