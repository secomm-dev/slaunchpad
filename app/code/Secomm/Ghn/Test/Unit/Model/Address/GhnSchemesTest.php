<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\GhnSchemes;

/**
 * TASK-RJFTPZ — GHN scheme catalog: two schemes, one identity per scheme, unknown rejected.
 */
class GhnSchemesTest extends TestCase
{
    public function testBothSchemesAreKnown(): void
    {
        GhnSchemes::assertKnown(GhnSchemes::GHN_ADMIN_2025);
        GhnSchemes::assertKnown(GhnSchemes::GHN_ADMIN_PRE_2025);

        $this->assertSame(
            [GhnSchemes::GHN_ADMIN_2025, GhnSchemes::GHN_ADMIN_PRE_2025],
            array_keys(GhnSchemes::all())
        );
    }

    public function testMaxDepthMatchesHierarchy(): void
    {
        $this->assertSame(2, GhnSchemes::maxDepth(GhnSchemes::GHN_ADMIN_2025));
        $this->assertSame(3, GhnSchemes::maxDepth(GhnSchemes::GHN_ADMIN_PRE_2025));
    }

    public function testUnknownSchemeThrows(): void
    {
        $this->expectException(LocalizedException::class);

        GhnSchemes::assertKnown('VN_ADMIN_2025');
    }
}
