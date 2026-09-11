<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Test\Unit\Model\Inbound;

use PHPUnit\Framework\TestCase;
use Secomm\Pancake\Model\Inbound\OrderPayloadParser;
use Secomm\Pancake\Model\ServiceCode;

class OrderPayloadParserTest extends TestCase
{
    public function testParseExtractsTrackingFromExtendUpdate(): void
    {
        $parser = new OrderPayloadParser();
        $update = $parser->parse([
            'id' => 77,
            'status' => 2,
            'partner_name' => 'Shopee Xpress',
            'tracking_link' => 'https://example.test/t',
            'partner' => [
                'extend_update' => [
                    ['tracking_id' => 'S58824.MB25'],
                ],
            ],
        ]);

        $this->assertNotNull($update);
        $this->assertSame(ServiceCode::CODE, $update->getServiceCode());
        $this->assertSame('77', $update->getExternalOrderId());
        $this->assertSame('2', $update->getRawStatus());
        $this->assertSame('S58824.MB25', $update->getTrackingNumber());
        $this->assertSame('Shopee Xpress', $update->getCarrierName());
    }

    public function testParseWebhookOrderResponseShape(): void
    {
        $parser = new OrderPayloadParser();
        $update = $parser->parse([
            'id' => 9001,
            'custom_id' => '000000046-7',
            'status' => 3,
            'updated_at' => '2026-09-11T03:00:00Z',
            'tracking_link' => 'https://track.example/abc',
            'partner' => [
                'partner_name' => 'GHN',
                'delivery_name' => 'Giao Hang Nhanh',
                'extend_update' => [
                    ['tracking_id' => 'GHN123456'],
                ],
            ],
        ]);

        $this->assertNotNull($update);
        $this->assertSame('9001', $update->getExternalOrderId());
        $this->assertSame('3', $update->getRawStatus());
        $this->assertSame('GHN', $update->getCarrierName());
        $this->assertSame('GHN123456', $update->getTrackingNumber());
        $this->assertSame('https://track.example/abc', $update->getTrackingUrl());
        $this->assertNotSame('', (string) $update->getEventId());
    }

    public function testParsePrefersPartnerNameOverDeliveryName(): void
    {
        $update = (new OrderPayloadParser())->parse([
            'id' => 1,
            'status' => 2,
            'partner' => [
                'partner_name' => 'Partner A',
                'delivery_name' => 'Delivery B',
            ],
        ]);

        $this->assertNotNull($update);
        $this->assertSame('Partner A', $update->getCarrierName());
    }

    public function testParseReturnsNullWithoutId(): void
    {
        $this->assertNull((new OrderPayloadParser())->parse(['status' => 2]));
    }
}
