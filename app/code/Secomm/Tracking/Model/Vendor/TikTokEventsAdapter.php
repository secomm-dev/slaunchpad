<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Vendor;

use Magento\Framework\HTTP\Client\Curl;
use Secomm\Tracking\Model\Config;
use Secomm\Tracking\Model\Event\TrackingEvent;

/**
 * FEAT-31X6N2 / SPEC §6.2 — TikTok Events API adapter.
 *
 * POST https://business-api.tiktok.com/open_api/v1.3/event/track/
 * Header Access-Token. purchase → CompletePayment; browser + server share
 * event_id so TikTok dedupes (spec §7). User block is hash-only ([BLOCK] PII).
 */
class TikTokEventsAdapter implements VendorAdapterInterface
{
    private const ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    private const EVENT_MAP = [
        TrackingEvent::EVENT_VIEW_ITEM => 'ViewContent',
        TrackingEvent::EVENT_ADD_TO_CART => 'AddToCart',
        TrackingEvent::EVENT_BEGIN_CHECKOUT => 'InitiateCheckout',
        TrackingEvent::EVENT_PURCHASE => 'CompletePayment',
        TrackingEvent::EVENT_REFUND => 'Refund', // TikTok has no native refund — custom event
    ];

    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl
    ) {
    }

    public function getVendorKey(): string
    {
        return 'tiktok';
    }

    public function isConfigured(?string $scopeCode = null): bool
    {
        return $this->config->isTiktokEnabled($scopeCode)
            && $this->config->getTiktokPixelId($scopeCode) !== ''
            && $this->config->getTiktokAccessToken($scopeCode) !== '';
    }

    public function mapEventName(string $eventName): ?string
    {
        return self::EVENT_MAP[$eventName] ?? null;
    }

    public function send(TrackingEvent $event, ?string $scopeCode = null): DeliveryResult
    {
        $vendorName = $this->mapEventName($event->event);
        if ($vendorName === null) {
            return new DeliveryResult(null, 'unsupported event ' . $event->event, false);
        }

        $payload = [
            'event_source' => 'web',
            'event_source_id' => $this->config->getTiktokPixelId($scopeCode),
            'data' => [
                [
                    'event' => $vendorName,
                    'event_time' => $event->eventTime,
                    'event_id' => $event->eventId,
                    'user' => $this->user($event),
                    'properties' => [
                        'currency' => $event->currency,
                        'value' => $event->value,
                        'order_id' => $event->orderId,
                        'contents' => array_map(
                            static fn (array $item): array => [
                                'content_id' => (string)$item['item_id'],
                                'content_type' => 'product',
                                'quantity' => (int)$item['quantity'],
                                'price' => (float)$item['price'],
                            ],
                            $event->toArray()['items']
                        ),
                    ],
                ],
            ],
        ];

        if ($this->config->isTiktokTestMode($scopeCode)) {
            // Debug mode: events land in Event Debug instead of live audiences.
            $payload['data'][0]['is_debug_event'] = true;
        }

        return $this->post($payload, $scopeCode);
    }

    /**
     * @param TrackingEvent $event
     * @return array<string, string>
     */
    private function user(TrackingEvent $event): array
    {
        $user = $event->user;
        $data = [];
        if ($user !== null) {
            $data['email'] = $user->emailSha256;
            $data['phone'] = $user->phoneSha256;
            $data['external_id'] = $user->externalIdSha256;
            $data['ttp'] = $user->ttp;
            $data['ttclid'] = $user->ttclid;
            $data['ip'] = $user->clientIp;
            $data['user_agent'] = $user->clientUserAgent;
        }

        return array_filter($data, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param array<string, mixed> $payload
     * @return DeliveryResult
     * @throws VendorSendException
     */
    private function post(array $payload, ?string $scopeCode): DeliveryResult
    {
        $this->curl->setOption(CURLOPT_TIMEOUT, 10);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Access-Token', $this->config->getTiktokAccessToken($scopeCode));
        $this->curl->post(self::ENDPOINT, (string)json_encode($payload, JSON_THROW_ON_ERROR));

        $status = $this->curl->getStatus();
        $body = (string)$this->curl->getBody();
        $decoded = json_decode($body, true);

        // TikTok answers 200 with code!=0 on logical errors (bad token, bad pixel).
        $code = is_array($decoded) ? (int)($decoded['code'] ?? 0) : 0;
        $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : 'unparsable response';

        if ($status < 200 || $status >= 300) {
            throw new VendorSendException(
                sprintf('TikTok Events API HTTP %d', $status),
                $status,
                $status >= 500,
                $this->summarize($decoded)
            );
        }
        if ($code !== 0) {
            // Auth/pixel errors (e.g. 40100/40102) are not retryable blindly.
            $authError = $code >= 40100 && $code < 40200;
            throw new VendorSendException(
                sprintf('TikTok Events API code %d: %s', $code, $message),
                $status,
                !$authError,
                $this->summarize($decoded)
            );
        }

        return new DeliveryResult($status, $this->summarize($decoded), false);
    }

    /**
     * @param array<string, mixed>|null $decoded
     * @return string
     */
    private function summarize(?array $decoded): string
    {
        if ($decoded === null) {
            return 'unparsable response';
        }

        $parts = [];
        if (isset($decoded['code'])) {
            $parts[] = 'code=' . (string)$decoded['code'];
        }
        if (isset($decoded['message'])) {
            $parts[] = 'message=' . substr((string)$decoded['message'], 0, 120);
        }
        if (isset($decoded['request_id'])) {
            $parts[] = 'request_id=' . (string)$decoded['request_id'];
        }

        return $parts === [] ? 'ok' : implode(' ', $parts);
    }
}
