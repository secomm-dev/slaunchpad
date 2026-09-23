<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Fallback;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\Fallback\FallbackRateRequest;

/**
 * TASK-XXBN5X — provider-neutral fallback request transport + invariants.
 */
class FallbackRateRequestTest extends TestCase
{
    public function testTransportsAllGenericFields(): void
    {
        $request = new FallbackRateRequest(
            countryId: 'VN',
            regionId: 521,
            postcode: '70000',
            weight: 1.5,
            subtotal: 1250000.0,
            qty: 3.0,
            storeId: 2,
            customerGroupId: 1
        );

        $this->assertSame('VN', $request->getCountryId());
        $this->assertSame(521, $request->getRegionId());
        $this->assertSame('70000', $request->getPostcode());
        $this->assertSame(1.5, $request->getWeight());
        $this->assertSame(1250000.0, $request->getSubtotal());
        $this->assertSame(3.0, $request->getQty());
        $this->assertSame(2, $request->getStoreId());
        $this->assertSame(1, $request->getCustomerGroupId());
    }

    public function testUnknownFieldsDefaultToNull(): void
    {
        $request = new FallbackRateRequest(
            countryId: null,
            regionId: null,
            postcode: null,
            weight: 0.0,
            subtotal: 0.0,
            qty: 0.0,
            storeId: 1
        );

        $this->assertNull($request->getCountryId());
        $this->assertNull($request->getRegionId());
        $this->assertNull($request->getPostcode());
        $this->assertNull($request->getCustomerGroupId());
        $this->assertSame(0.0, $request->getWeight());
    }

    public function testZeroCartDimensionsAreValid(): void
    {
        $request = new FallbackRateRequest(null, null, null, 0.0, 0.0, 0.0, 1);

        $this->assertSame(0.0, $request->getWeight());
        $this->addToAssertionCount(1);
    }

    public function testRejectsNegativeWeight(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must not be negative');
        new FallbackRateRequest('VN', null, null, -1.0, 0.0, 0.0, 1);
    }

    public function testRejectsNegativeSubtotal(): void
    {
        $this->expectException(\LogicException::class);
        new FallbackRateRequest('VN', null, null, 0.0, -0.5, 0.0, 1);
    }

    public function testRejectsNegativeQty(): void
    {
        $this->expectException(\LogicException::class);
        new FallbackRateRequest('VN', null, null, 0.0, 0.0, -1.0, 1);
    }
}
