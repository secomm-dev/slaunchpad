<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Test\Unit\Event;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\TestCase;
use Secomm\Tracking\Model\Event\EventNormalizer;
use Secomm\Tracking\Model\Event\TrackingEvent;
use Secomm\Tracking\Model\Hash\UserDataHasher;

/**
 * TASK-NNKTRM AC-1/AC-3/AC-4 — event mapping, event_id determinism, PII leakage
 * (SPEC §4, §7; [BLOCK] plain identifiers never serialize).
 *
 * Uses real Order/Order\Item data objects (no DB) — matches what observers pass.
 */
class EventNormalizerTest extends TestCase
{
    private const PLAIN_EMAIL = 'shopper.launchpad@example.vn';
    private const PLAIN_PHONE = '0901234567';

    private EventNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new EventNormalizer(new UserDataHasher());
    }

    /**
     * @return Order
     */
    private function order(): Order
    {
        $parent = new OrderItem();
        $parent->setData([
            'sku' => 'TEE-RED-M',
            'name' => 'Launchpad Tee Red M',
            'price' => 550000.0,
            'qty_ordered' => 3,
            'parent_item_id' => null,
            'product_type' => 'simple',
        ]);

        $child = new OrderItem();
        $child->setData([
            'sku' => 'TEE-RED',
            'name' => 'Launchpad Tee Red',
            'price' => 550000.0,
            'qty_ordered' => 3,
            'parent_item_id' => 77,
            'product_type' => 'simple',
        ]);

        $address = new Address();
        $address->setData([
            'email' => self::PLAIN_EMAIL,
            'telephone' => self::PLAIN_PHONE,
            'country_id' => 'VN',
        ]);

        $order = new Order();
        $order->setData([
            'increment_id' => '100000123',
            'created_at' => '2026-08-24 10:00:00',
            'order_currency_code' => 'VND',
            'grand_total' => 1650000.0,
            'customer_id' => 42,
            'customer_email' => 'fallback@example.vn',
            'remote_ip' => '203.0.113.9',
        ]);
        $order->setBillingAddress($address);
        $order->setData('items', [$parent, $child]);

        return $order;
    }

    public function testPurchaseMapsContractFields(): void
    {
        $event = $this->normalizer->fromOrder($this->order());

        $this->assertSame(TrackingEvent::EVENT_PURCHASE, $event->event);
        $this->assertSame('purchase-100000123', $event->eventId);
        $this->assertSame('100000123', $event->orderId);
        $this->assertSame('VND', $event->currency);
        $this->assertSame(1650000.0, $event->value);
        $this->assertSame(TrackingEvent::SOURCE_SERVER, $event->source);

        // Children of configurable stay nested — only the visible parent ships.
        $this->assertCount(1, $event->items);
        $this->assertSame('TEE-RED-M', $event->items[0]->itemId);
        $this->assertSame(550000.0, $event->items[0]->price);
        $this->assertSame(3.0, $event->items[0]->quantity);
    }

    public function testPurchaseHashesUserIdentifiers(): void
    {
        $event = $this->normalizer->fromOrder($this->order());
        $user = $event->user;

        $this->assertNotNull($user);
        $this->assertSame(hash('sha256', self::PLAIN_EMAIL), $user->emailSha256);
        $this->assertSame(hash('sha256', '+84901234567'), $user->phoneSha256);
        $this->assertSame(hash('sha256', '42'), $user->externalIdSha256);
        $this->assertSame('203.0.113.9', $user->clientIp);
    }

    public function testPurchaseEventIdIsDeterministic(): void
    {
        $order = $this->order();
        $first = $this->normalizer->fromOrder($order)->eventId;
        $second = $this->normalizer->fromOrder($order)->eventId;
        $this->assertSame($first, $second);

        $other = $this->order();
        $other->setData('increment_id', '100000124');
        $this->assertNotSame($first, $this->normalizer->fromOrder($other)->eventId);
    }

    public function testGuestOrderStillHashesBillingIdentity(): void
    {
        $order = $this->order();
        $order->setData('customer_id', null);
        $order->unsetData('billing_address');
        $order->setBillingAddress(null);

        $event = $this->normalizer->fromOrder($order);
        $this->assertNotNull($event->user);
        $this->assertSame(hash('sha256', 'fallback@example.vn'), $event->user->emailSha256);
        $this->assertNull($event->user->externalIdSha256);
    }

    public function testRefundMapsCreditmemoFields(): void
    {
        $orderItem = new OrderItem();
        $orderItem->setData(['sku' => 'TEE-RED-M', 'name' => 'Launchpad Tee Red M', 'parent_item_id' => null]);

        $cmItem = new CreditmemoItem();
        $cmItem->setData([
            'sku' => 'TEE-RED-M',
            'name' => 'Launchpad Tee Red M',
            'price' => 550000.0,
            'qty' => 1,
        ]);
        $cmItem->setOrderItem($orderItem);

        $creditmemo = new Creditmemo();
        $creditmemo->setData([
            'increment_id' => '100000001',
            'grand_total' => 550000.0,
            'order_id' => 99,
        ]);
        $creditmemo->setOrder($this->order());
        $creditmemo->setData('items', [$cmItem]);

        $event = $this->normalizer->fromCreditmemo($creditmemo);

        $this->assertSame(TrackingEvent::EVENT_REFUND, $event->event);
        $this->assertSame('refund-100000001', $event->eventId);
        $this->assertSame(550000.0, $event->value);
        $this->assertSame('VND', $event->currency);
        $this->assertSame('100000123', $event->orderId); // from the order, not entity id
        $this->assertCount(1, $event->items);
    }

    public function testBrowserEventIdIsStablePerEntityPerDay(): void
    {
        $a = $this->normalizer->browserEventId('view_item', 55);
        $b = $this->normalizer->browserEventId('view_item', 55);
        $c = $this->normalizer->browserEventId('view_item', 56);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertMatchesRegularExpression('/^view_item-55-\d{8}$/', $a);
    }

    /**
     * [BLOCK] PII rule — plain identifiers must never appear in the serialized
     * payload (TASK-NNKTRM AC-4).
     */
    public function testSerializedPayloadNeverContainsPlainIdentifiers(): void
    {
        $json = $this->normalizer->fromOrder($this->order())->toJson();

        $this->assertStringNotContainsString(self::PLAIN_EMAIL, $json);
        $this->assertStringNotContainsString(self::PLAIN_PHONE, $json);
        $this->assertStringNotContainsString('+84901234567', $json); // normalized plain form either
        $this->assertStringNotContainsString('"customer_id"', $json);
    }

    public function testAbsentUserDataFieldsStayAbsent(): void
    {
        $order = new Order();
        $order->setData([
            'increment_id' => '100000126',
            'created_at' => '2026-08-24 12:00:00',
            'order_currency_code' => 'VND',
            'grand_total' => 1.0,
            'customer_id' => null,
            'customer_email' => '',
            'remote_ip' => null,
            'items' => [],
        ]);

        $user = $this->normalizer->fromOrder($order)->user;
        $this->assertNotNull($user);
        $array = $user->toArray();
        $this->assertArrayNotHasKey('client_ip', $array);
        $this->assertArrayNotHasKey('fbp', $array);
        foreach ($array as $value) {
            $this->assertNotSame('', $value);
        }
    }
}
