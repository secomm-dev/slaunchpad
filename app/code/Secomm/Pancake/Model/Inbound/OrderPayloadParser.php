<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Inbound;

use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\Pancake\Model\ServiceCode;

/**
 * Parse POS order JSON (GET order or WebhookOrderResponse) into core InboundUpdate.
 */
class OrderPayloadParser
{
    /**
     * Parse a Pancake order object into an inbound update DTO.
     *
     * @param array<string, mixed> $order POS order / WebhookOrderResponse object
     */
    public function parse(array $order): ?InboundUpdate
    {
        if (isset($order['order']) && is_array($order['order'])
            && !isset($order['id']) && !isset($order['order_id']) && !isset($order['custom_id'])
        ) {
            return $this->parse($order['order']);
        }

        // Prefer POS numeric id for secomm_fulfillment_export.external_order_id lookup.
        $id = $order['id'] ?? $order['order_id'] ?? null;
        if ($id === null || $id === '') {
            $id = $order['custom_id'] ?? null;
        }
        if ($id === null || $id === '') {
            return null;
        }

        $rawStatus = '';
        foreach (['status', 'order_status'] as $key) {
            if (isset($order[$key]) && $order[$key] !== '' && !is_array($order[$key])) {
                $rawStatus = (string) $order[$key];
                break;
            }
        }
        $tracking = $this->extractTracking($order);
        $eventId = sha1(
            ServiceCode::CODE . '|' . $id . '|' . $rawStatus . '|'
            . ($tracking['number'] ?? '') . '|' . ($order['updated_at'] ?? '')
        );

        return new InboundUpdate(
            ServiceCode::CODE,
            (string) $id,
            $rawStatus,
            $eventId,
            $tracking['carrier'],
            $tracking['number'],
            $tracking['url']
        );
    }

    /**
     * Extract carrier / tracking number / tracking URL from WebhookOrderResponse fields.
     *
     * @param array<string, mixed> $order POS order object
     * @return array{carrier: ?string, number: ?string, url: ?string}
     */
    private function extractTracking(array $order): array
    {
        $carrier = null;
        $url = isset($order['tracking_link']) ? (string) $order['tracking_link'] : null;
        $number = null;

        $partner = $order['partner'] ?? null;
        if (is_array($partner)) {
            if (!empty($partner['partner_name'])) {
                $carrier = (string) $partner['partner_name'];
            } elseif (!empty($partner['delivery_name'])) {
                $carrier = (string) $partner['delivery_name'];
            }
            $updates = $partner['extend_update'] ?? null;
            if (is_array($updates)) {
                foreach ($updates as $row) {
                    if (is_array($row) && !empty($row['tracking_id'])) {
                        $number = (string) $row['tracking_id'];
                    }
                }
            }
        }

        // Legacy flat fields (some GET shapes).
        if ($carrier === null && isset($order['partner_name']) && $order['partner_name'] !== '') {
            $carrier = (string) $order['partner_name'];
        }
        if ($carrier === null && isset($order['delivery_name']) && $order['delivery_name'] !== '') {
            $carrier = (string) $order['delivery_name'];
        }

        return [
            'carrier' => $carrier !== null && $carrier !== '' ? $carrier : null,
            'number' => $number !== null && $number !== '' ? $number : null,
            'url' => $url !== null && $url !== '' ? $url : null,
        ];
    }
}
