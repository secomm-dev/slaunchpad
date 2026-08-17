<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\GhtkAddressMapImport;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\GhtkAddressMapImport\Validator;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testAcceptsValidRows(): void
    {
        $rows = [
            ['country_id' => 'VN', 'region_id' => '1157', 'ward_id' => '1', 'ghtk_province' => 'Hà Nội', 'ghtk_district' => 'Hoàn Kiếm', 'ghtk_ward' => 'Phường A', 'is_active' => '1'],
            ['country_id' => 'vn', 'region_id' => '2', 'ward_id' => '3', 'ghtk_province' => 'Đà Nẵng', 'ghtk_district' => '', 'ghtk_ward' => 'Phường B', 'is_active' => ''],
        ];

        $result = $this->validator->validate($rows);

        $this->assertEmpty($result['errors']);
        $this->assertCount(2, $result['accepted']);
        $this->assertSame('VN', $result['accepted'][0]['country_id']); // normalised uppercase
        $this->assertSame('VN', $result['accepted'][1]['country_id']); // 'vn' upper-cased
        $this->assertSame(1, $result['accepted'][1]['is_active']); // default active when empty
        $this->assertNull($result['accepted'][1]['ghtk_district']); // empty district -> null
        $this->assertSame(0, $result['skipped']);
    }

    public function testRejectsBadCountry(): void
    {
        $rows = [['country_id' => 'Vietnam', 'region_id' => '1', 'ward_id' => '1', 'ghtk_province' => 'p', 'ghtk_district' => '', 'ghtk_ward' => 'w', 'is_active' => '1']];

        $result = $this->validator->validate($rows);

        $this->assertNotEmpty($result['errors']);
        $this->assertEmpty($result['accepted']);
    }

    public function testRejectsMissingRequiredGhtkWard(): void
    {
        $rows = [['country_id' => 'VN', 'region_id' => '1', 'ward_id' => '1', 'ghtk_province' => 'p', 'ghtk_district' => '', 'ghtk_ward' => '', 'is_active' => '1']];

        $result = $this->validator->validate($rows);

        $this->assertNotEmpty($result['errors']);
        $this->assertEmpty($result['accepted']);
    }

    public function testRejectsNonPositiveIds(): void
    {
        $rows = [['country_id' => 'VN', 'region_id' => '0', 'ward_id' => '1', 'ghtk_province' => 'p', 'ghtk_district' => '', 'ghtk_ward' => 'w', 'is_active' => '1']];

        $result = $this->validator->validate($rows);

        $this->assertNotEmpty($result['errors']);
    }

    public function testDuplicateKeySkipsSecondOccurrence(): void
    {
        $rows = [
            ['country_id' => 'VN', 'region_id' => '1', 'ward_id' => '1', 'ghtk_province' => 'p', 'ghtk_district' => '', 'ghtk_ward' => 'w', 'is_active' => '1'],
            ['country_id' => 'VN', 'region_id' => '1', 'ward_id' => '1', 'ghtk_province' => 'p', 'ghtk_district' => '', 'ghtk_ward' => 'w2', 'is_active' => '1'],
        ];

        $result = $this->validator->validate($rows);

        $this->assertEmpty($result['errors']);
        $this->assertCount(1, $result['accepted']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testRejectsBadIsActive(): void
    {
        $rows = [['country_id' => 'VN', 'region_id' => '1', 'ward_id' => '1', 'ghtk_province' => 'p', 'ghtk_district' => '', 'ghtk_ward' => 'w', 'is_active' => '5']];

        $result = $this->validator->validate($rows);

        $this->assertNotEmpty($result['errors']);
    }
}
