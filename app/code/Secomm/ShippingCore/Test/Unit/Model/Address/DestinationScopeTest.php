<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\DestinationScope;

/**
 * TASK-8MQHJX (Phase B) — destination scope domain values: exactly ALL/SELECTED_ZONES;
 * ShippingCore never hardcodes zone identities.
 */
class DestinationScopeTest extends TestCase
{
    public function testExactlyTwoKnownScopes(): void
    {
        $this->assertSame(['ALL', 'SELECTED_ZONES'], DestinationScope::all());
    }

    public function testExistsAcceptsKnownScopes(): void
    {
        $this->assertTrue(DestinationScope::exists(DestinationScope::ALL));
        $this->assertTrue(DestinationScope::exists(DestinationScope::SELECTED_ZONES));
    }

    public function testExistsRejectsUnknownAndZoneIdentities(): void
    {
        // zone identities are merchant data — never domain values
        $this->assertFalse(DestinationScope::exists('HCM_INNER'));
        $this->assertFalse(DestinationScope::exists(''));
        $this->assertFalse(DestinationScope::exists('all'));
    }

    public function testAssertKnownPassesForKnownScopes(): void
    {
        DestinationScope::assertKnown(DestinationScope::ALL);
        DestinationScope::assertKnown(DestinationScope::SELECTED_ZONES);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertKnownThrowsForUnknownScope(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown destination scope "HCM_INNER"');

        DestinationScope::assertKnown('HCM_INNER');
    }
}
