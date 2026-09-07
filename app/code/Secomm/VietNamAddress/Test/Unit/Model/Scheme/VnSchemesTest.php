<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Scheme;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — scheme catalog integrity: immutable identities, profile codes,
 * legacy alias resolution (upgrade without purge), catalog assertions.
 */
class VnSchemesTest extends TestCase
{
    public function testCatalogContainsBothCanonicalSchemes(): void
    {
        $catalog = VnSchemes::catalog();

        $this->assertArrayHasKey(VnSchemes::VN_ADMIN_2025, $catalog);
        $this->assertArrayHasKey(VnSchemes::VN_ADMIN_PRE_2025, $catalog);
        $this->assertSame('vn_admin_2025', VnSchemes::profileCode(VnSchemes::VN_ADMIN_2025));
        $this->assertSame('vn_admin_pre_2025', VnSchemes::profileCode(VnSchemes::VN_ADMIN_PRE_2025));
        $this->assertSame(2, $catalog[VnSchemes::VN_ADMIN_2025]['level_count']);
        $this->assertSame(3, $catalog[VnSchemes::VN_ADMIN_PRE_2025]['level_count']);
    }

    public function testCountsContractMatchesDatasets(): void
    {
        $catalog = VnSchemes::catalog();

        $this->assertSame(['regions' => 34, 'depth1' => 3321, 'depth2' => 0], $catalog[VnSchemes::VN_ADMIN_2025]['counts']);
        $this->assertSame(['regions' => 63, 'depth1' => 699, 'depth2' => 10595], $catalog[VnSchemes::VN_ADMIN_PRE_2025]['counts']);
        $this->assertSame(['groups' => 19, 'rows' => 38], $catalog[VnSchemes::VN_ADMIN_PRE_2025]['collision']);
        $this->assertNull($catalog[VnSchemes::VN_ADMIN_2025]['collision']);
    }

    public function testLegacyProfileAliasesResolveToCanonicalSchemes(): void
    {
        $this->assertSame(VnSchemes::VN_ADMIN_2025, VnSchemes::schemeForProfile('vn_current'));
        $this->assertSame(VnSchemes::VN_ADMIN_PRE_2025, VnSchemes::schemeForProfile('vn_legacy'));
        $this->assertSame(VnSchemes::VN_ADMIN_2025, VnSchemes::schemeForProfile('vn_admin_2025'));
        $this->assertNull(VnSchemes::schemeForProfile('unknown_profile'));
    }

    public function testAssertKnownThrowsForUnknownScheme(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown Vietnam administrative scheme');
        VnSchemes::assertKnown('vn_current'); // relative names are never identities
    }
}
