<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeFunction\Test\Unit\Model\Inbound;

use PHPUnit\Framework\TestCase;
use Secomm\PancakeFunction\Model\Inbound\OrderPayloadParser;

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
        $this->assertSame('77', $update->getExternalOrderId());
        $this->assertSame('2', $update->getRawStatus());
        $this->assertSame('S58824.MB25', $update->getTrackingNumber());
        $this->assertSame('Shopee Xpress', $update->getCarrierName());
    }

    public function testParseReturnsNullWithoutId(): void
    {
        $this->assertNull((new OrderPayloadParser())->parse(['status' => 2]));
    }
}
