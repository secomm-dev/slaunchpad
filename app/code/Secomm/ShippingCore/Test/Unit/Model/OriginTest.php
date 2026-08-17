<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Origin;

class OriginTest extends TestCase
{
    public function testCarrierMetadataDottedKey(): void
    {
        $origin = new Origin(
            sourceCode: 'hcm-wh',
            countryId: 'VN',
            regionId: 601,
            province: 'Ho Chi Minh',
            district: null,
            ward: 'Phường Bến Nghé',
            street: '1 Nguyễn Huệ',
            postcode: null,
            telephone: null,
            contactName: null,
            metadata: ['ghtk.pick_address_id' => 'paid-123', 'ghn.shop_id' => 456]
        );

        $this->assertTrue($origin->hasMetadata('ghtk.pick_address_id'));
        $this->assertSame('paid-123', $origin->getMetadata('ghtk.pick_address_id'));
        $this->assertSame(456, $origin->getMetadata('ghn.shop_id'));
        $this->assertFalse($origin->hasMetadata('ghtk.unknown'));
        $this->assertNull($origin->getMetadata('ghtk.unknown'));
        $this->assertSame('fallback', $origin->getMetadata('ghtk.unknown', 'fallback'));
        $this->assertNull($origin->getDistrict()); // nullable by design (Launchpad VN model)
        $this->assertSame('hcm-wh', $origin->getSourceCode());
    }
}
