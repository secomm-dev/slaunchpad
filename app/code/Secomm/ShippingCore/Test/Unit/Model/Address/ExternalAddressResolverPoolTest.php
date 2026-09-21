<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\ExternalAddressResolverInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Model\Address\ExternalAddressResolverPool;

/**
 * TASK-AQT7V3 — zero-provider pool is a valid, safe state; DI order preserved; contract enforced.
 */
class ExternalAddressResolverPoolTest extends TestCase
{
    public function testEmptyPoolIsValid(): void
    {
        $pool = new ExternalAddressResolverPool();

        $this->assertSame([], $pool->getResolvers());
    }

    public function testPreservesDiRegistrationOrder(): void
    {
        $first = $this->createDummyResolver();
        $second = $this->createDummyResolver();

        $pool = new ExternalAddressResolverPool([$first, $second]);

        $this->assertSame([$first, $second], $pool->getResolvers());
    }

    public function testRejectsEntryNotImplementingResolverContract(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement');
        new ExternalAddressResolverPool([new \stdClass()]);
    }

    private function createDummyResolver(): ExternalAddressResolverInterface
    {
        return new class implements ExternalAddressResolverInterface {
            public function isAvailable(): bool
            {
                return false;
            }

            public function resolve(ShippingAddressResolutionContextInterface $context): ?string
            {
                return null;
            }
        };
    }
}
