<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Capability;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Capability\GhnRateCapabilityAdapter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-FMBBSD (GHN-C slice 2) — the module-private legacy-shape bridge must expose exactly the
 * RATE view of the per-operation capability (the shape RuntimeAddressContextBuilder consumes).
 */
class GhnRateCapabilityAdapterTest extends TestCase
{
    public function testExposesRateRequiredSchemeInLegacyShape(): void
    {
        $adapter = new GhnRateCapabilityAdapter(new GhnAddressCapability());

        $this->assertSame(VnSchemes::VN_ADMIN_PRE_2025, $adapter->getRequiredScheme());
        $this->assertFalse($adapter->supportsTextualFallback());
    }
}
