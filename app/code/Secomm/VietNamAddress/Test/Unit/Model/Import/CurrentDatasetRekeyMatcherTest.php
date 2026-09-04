<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model\Import;

use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Model\Import\CurrentDatasetRekeyMatcher;

/**
 * TASK-ADT94K — re-key bridge matcher: name normalisation keys (real edge cases from the
 * datasets) + primary vi / fallback en matching with ambiguity failure.
 */
class CurrentDatasetRekeyMatcherTest extends TestCase
{
    private CurrentDatasetRekeyMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new CurrentDatasetRekeyMatcher();
    }

    public function testViKeyStripsAdministrativePrefixes(): void
    {
        $this->assertSame('Hoàn Kiếm', $this->matcher->viKey('Phường Hoàn Kiếm'));
        $this->assertSame('Yên Viên', $this->matcher->viKey('Thị trấn Yên Viên'));
        $this->assertSame('Gia Lâm', $this->matcher->viKey('Huyện Gia Lâm'));
        $this->assertSame('Hà Nội', $this->matcher->viKey('Thành phố Hà Nội'));
        $this->assertSame('Côn Đảo', $this->matcher->viKey('Đặc khu Côn Đảo'));
        // Real dataset case: the old name "Xã Hội Thịnh" folds to the new "Hội Thịnh".
        $this->assertSame('Hội Thịnh', $this->matcher->viKey('Xã Hội Thịnh'));
    }

    public function testEnKeyStripsTypeWordsBothSidesAndFolds(): void
    {
        $this->assertSame('hoan kiem', $this->matcher->enKey('Hoan Kiem Ward'));
        $this->assertSame('chanh phu hoa', $this->matcher->enKey('Ward Chanh Phu Hoa'));
        $this->assertSame('prosperous', $this->matcher->enKey('Prosperous Society'));
        $this->assertSame('son', $this->matcher->enKey('Son Society'));
        $this->assertSame('co to', $this->matcher->enKey('Co To Special Economic Zone'));
        $this->assertSame('cat hai', $this->matcher->enKey('Cat Hai Special Zone'));
        $this->assertSame('ho chi minh', $this->matcher->enKey('Ho Chi Minh City'));
        $this->assertSame('quan ba', $this->matcher->enKey("Quan Ba\u{200B}\u{200B}Commune"));
    }

    public function testMatchesPrimaryViKeyBeforeEnFallback(): void
    {
        $this->matcher->buildIndex([
            $this->cityRow('16', 'VNC-DONG-TIEN', 'Đồng Tiến', 'Dong Tien'),
            $this->cityRow('16', 'VNC-DONG-TIEN-OLD', 'Đông Tiến', 'Dong Tien'),
        ]);

        // Old-format DB row for Xã Đông Tiến resolves via the exact vi key.
        $this->assertSame(
            'VNC-DONG-TIEN-OLD',
            $this->matcher->match('16', 'Xã Đông Tiến', 'Dong Tien Commune')
        );
        $this->assertSame(
            'VNC-DONG-TIEN',
            $this->matcher->match('16', 'Phường Đồng Tiến', 'Dong Tien Ward')
        );
    }

    public function testEnFallbackResolvesWhenViNameMissing(): void
    {
        $this->matcher->buildIndex([
            $this->cityRow('01', 'VNC-HOAN-KIEM', 'Hoàn Kiếm', 'Hoan Kiem'),
        ]);

        $this->assertSame('VNC-HOAN-KIEM', $this->matcher->match('01', '', 'Hoan Kiem Ward'));
    }

    public function testEnFallbackAmbiguityFailsLoud(): void
    {
        $this->matcher->buildIndex([
            $this->cityRow('16', 'VNC-A', 'Đồng Tiến', 'Dong Tien'),
            $this->cityRow('16', 'VNC-B', 'Đông Tiến', 'Dong Tien'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ambiguous re-key');
        $this->matcher->match('16', '', 'Dong Tien Ward');
    }

    public function testReturnsNullWhenNothingMatches(): void
    {
        $this->matcher->buildIndex([
            $this->cityRow('01', 'VNC-HOAN-KIEM', 'Hoàn Kiếm', 'Hoan Kiem'),
        ]);

        $this->assertNull($this->matcher->match('01', 'Phường Không Tồn Tại', 'Nowhere Ward'));
        $this->assertNull($this->matcher->match('99', 'Phường Hoàn Kiếm', 'Hoan Kiem Ward'));
    }

    public function testRegionBridgeMatchesLegacyTypeWordedNames(): void
    {
        $this->matcher->buildRegionIndex([
            $this->regionRow('VN-11', 'Hà Nội', 'Hanoi'),
            $this->regionRow('VN-23', 'Hải Phòng', 'Hai Phong'),
        ]);

        // Legacy rows from VN_Address_2Level.csv: English default names carry type words,
        // vi names carry administrative prefixes ("Tp" abbreviations only match via en).
        $this->assertSame('VN-11', $this->matcher->matchRegion('Hanoi City', 'Thành phố Hà Nội'));
        $this->assertSame('VN-23', $this->matcher->matchRegion('Hai Phong City', 'Tp Hải Phòng'));
    }

    public function testRegionBridgeFallsBackToViKeyWhenEnglishNameDrifts(): void
    {
        $this->matcher->buildRegionIndex([
            $this->regionRow('VN-11', 'Hà Nội', 'Ha Noi'),
        ]);

        // English side misses ("Hanoi" ≠ "Ha Noi"), vi side still resolves.
        $this->assertSame('VN-11', $this->matcher->matchRegion('Hanoi City', 'Thành phố Hà Nội'));
    }

    public function testRegionBridgeAmbiguityFailsLoud(): void
    {
        $this->matcher->buildRegionIndex([
            $this->regionRow('VN-11', 'Hà Nội', 'Hanoi'),
            $this->regionRow('VN-12', 'Hà Nội Mới', 'Hanoi'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ambiguous region re-key');
        $this->matcher->matchRegion('Hanoi City', '');
    }

    public function testRegionBridgeReturnsNullWhenNothingMatches(): void
    {
        $this->matcher->buildRegionIndex([
            $this->regionRow('VN-11', 'Hà Nội', 'Hanoi'),
        ]);

        $this->assertNull($this->matcher->matchRegion('Nowhere Province', 'Tỉnh Không Tồn Tại'));
    }

    /**
     * @return array<string, string|int>
     */
    private function regionRow(string $regionCode, string $nameVi, string $nameEn): array
    {
        return ['line' => 1, 'region_code' => $regionCode, 'name_vi' => $nameVi, 'name_en' => $nameEn];
    }

    /**
     * @return array<string, string|int>
     */
    private function cityRow(string $regionCode, string $code, string $nameVi, string $nameEn): array
    {
        return [
            'line' => 1, 'entity_type' => 'city', 'region_code' => $regionCode,
            'code' => $code, 'parent_code' => '', 'name_vi' => $nameVi, 'name_en' => $nameEn,
        ];
    }
}
