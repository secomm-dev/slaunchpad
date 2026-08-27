<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Model\Event;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Secomm\Tracking\Model\Hash\UserDataHasher;

/**
 * FEAT-31X6N2 / SPEC-FEAT-31X6N2 §4, §6.4, §7 — builds TrackingEvents from Magento entities.
 *
 * Pure server-side logic: no HTTP, no DB writes. Deterministic event ids:
 *   purchase-{order increment_id} · refund-{creditmemo increment_id}
 * so the browser dataLayer (success page) and the server outbox share the same
 * dedup key (DEC-FEAT31X6N2-001 D3).
 */
class EventNormalizer
{
    public function __construct(
        private readonly UserDataHasher $hasher
    ) {
    }

    /**
     * Purchase from a confirmed order. Value = grand_total in the order's display
     * currency; items = visible items (children of configurable/bundle stay nested).
     */
    public function fromOrder(Order $order): TrackingEvent
    {
        return new TrackingEvent(
            event: TrackingEvent::EVENT_PURCHASE,
            eventId: $this->purchaseEventId((string)$order->getIncrementId()),
            eventTime: $this->eventTime($order->getCreatedAt()),
            currency: (string)$order->getOrderCurrencyCode(),
            value: (float)$order->getGrandTotal(),
            orderId: (string)$order->getIncrementId(),
            items: $this->orderItems($order),
            user: $this->userDataFromOrder($order),
            consent: ['analytics' => true, 'marketing' => true],
            source: TrackingEvent::SOURCE_SERVER
        );
    }

    /**
     * Refund from a creditmemo — one event per creditmemo (spec §4 assumption):
     * value = creditmemo grand_total (display), id = refund-{cm increment_id}.
     */
    public function fromCreditmemo(Creditmemo $creditmemo): TrackingEvent
    {
        $order = $creditmemo->getOrder();
        $orderId = $order !== null ? (string)$order->getIncrementId() : (string)$creditmemo->getOrderId();

        $items = [];
        foreach ($creditmemo->getAllItems() as $cmItem) {
            if ((float)$cmItem->getQty() <= 0) {
                continue;
            }
            $orderItem = $cmItem->getOrderItem();
            if ($orderItem !== null && (bool)$orderItem->getParentItem()) {
                continue; // children stay nested under the visible parent
            }
            $items[] = new Item(
                itemId: (string)($orderItem !== null ? $orderItem->getSku() : $cmItem->getSku()),
                itemName: (string)$cmItem->getName(),
                itemCategory: null,
                price: (float)$cmItem->getPrice(),
                quantity: (float)$cmItem->getQty()
            );
        }

        return new TrackingEvent(
            event: TrackingEvent::EVENT_REFUND,
            eventId: $this->refundEventId((string)$creditmemo->getIncrementId()),
            eventTime: time(),
            currency: $order !== null ? (string)$order->getOrderCurrencyCode() : '',
            value: (float)$creditmemo->getGrandTotal(),
            orderId: $orderId,
            items: $items,
            user: $order !== null ? $this->userDataFromOrder($order) : null,
            consent: ['analytics' => true, 'marketing' => true],
            source: TrackingEvent::SOURCE_SERVER
        );
    }

    /**
     * Deterministic id shared with the success-page dataLayer (spec §7).
     */
    public function purchaseEventId(string $incrementId): string
    {
        return 'purchase-' . $incrementId;
    }

    /**
     * Deterministic id — retry-safe resend, no browser counterpart needed.
     */
    public function refundEventId(string $incrementId): string
    {
        return 'refund-' . $incrementId;
    }

    /**
     * Deterministic id for browser-context events: stable per entity per day,
     * so a cached category page re-render pushes the same id (FPC-safe, spec §7).
     */
    public function browserEventId(string $event, int|string $entityId): string
    {
        return sprintf('%s-%s-%s', $event, $entityId, gmdate('Ymd'));
    }

    /**
     * @param Order $order
     * @return Item[]
     */
    private function orderItems(Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            if ((float)$item->getQtyOrdered() <= 0) {
                continue;
            }
            $items[] = new Item(
                itemId: (string)$item->getSku(),
                itemName: (string)$item->getName(),
                itemCategory: null,
                price: (float)$item->getPrice(),
                quantity: (float)$item->getQtyOrdered()
            );
        }

        return $items;
    }

    /**
     * Hashed user data from the billing address + customer id. Plain values never
     * leave this method ([BLOCK] PII rule — spec §6.4).
     */
    private function userDataFromOrder(Order $order): UserData
    {
        $address = $order->getBillingAddress();
        $country = $address !== null ? (string)$address->getCountryId() : null;
        $customerId = $order->getCustomerId();

        return new UserData(
            emailSha256: $this->hasher->hashEmail($address?->getEmail() ?: (string)$order->getCustomerEmail()),
            phoneSha256: $this->hasher->hashPhone($address?->getTelephone(), $country),
            externalIdSha256: $customerId ? $this->hasher->hashExternalId($customerId) : null,
            clientIp: $order->getRemoteIp(),
            clientUserAgent: null
        );
    }

    /**
     * Order created_at (store TZ string) → unix seconds; falls back to now.
     */
    private function eventTime(?string $createdAt): int
    {
        if ($createdAt === null || $createdAt === '') {
            return time();
        }
        $ts = strtotime($createdAt);
        return $ts === false ? time() : $ts;
    }
}
