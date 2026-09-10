<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Inbound;

use Secomm\FulfillmentCore\Api\Data\InboundUpdate;
use Secomm\Pancake\Model\Order\PancakeOrderExporter;

/**
 * Parse POS order JSON (GET order or webhook body) into core InboundUpdate.
 *
 * @param array<string, mixed> $order POS order object
 */
class OrderPayloadParser
{
    /**
     * @param array<string, mixed> $order
     */
    public function parse(array $order): ?InboundUpdate
    {
        $id = $order['id'] ?? $order['order_id'] ?? $order['custom_id'] ?? null;
        if ($id === null || $id === '') {
            if (isset($order['order']) && is_array($order['order'])) {
                return $this->parse($order['order']);
            }
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
            PancakeOrderExporter::SERVICE_CODE . '|' . $id . '|' . $rawStatus . '|'
            . ($tracking['number'] ?? '') . '|' . ($order['updated_at'] ?? '')
        );

        return new InboundUpdate(
            PancakeOrderExporter::SERVICE_CODE,
            (string) $id,
            $rawStatus,
            $eventId,
            $tracking['carrier'],
            $tracking['number'],
            $tracking['url']
        );
    }

    /**
     * @param array<string, mixed> $order
     * @return array{carrier: ?string, number: ?string, url: ?string}
     */
    private function extractTracking(array $order): array
    {
        $carrier = isset($order['partner_name']) ? (string) $order['partner_name'] : null;
        if ($carrier === null && isset($order['delivery_name'])) {
            $carrier = (string) $order['delivery_name'];
        }

        $url = isset($order['tracking_link']) ? (string) $order['tracking_link'] : null;
        $number = null;

        $partner = $order['partner'] ?? null;
        if (is_array($partner)) {
            if ($carrier === null && isset($partner['partner_name'])) {
                $carrier = (string) $partner['partner_name'];
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

        return [
            'carrier' => $carrier !== '' ? $carrier : null,
            'number' => $number !== '' ? $number : null,
            'url' => $url !== '' ? $url : null,
        ];
    }
}
