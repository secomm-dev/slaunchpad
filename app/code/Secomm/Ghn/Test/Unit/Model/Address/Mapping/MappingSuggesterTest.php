<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Mapping;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Dataset\MappingCsv;
use Secomm\Ghn\Model\Address\Import\CsvReader;
use Secomm\Ghn\Model\Address\Mapping\MappingMatcher;
use Secomm\Ghn\Model\Address\Mapping\MappingSuggester;
use Secomm\Ghn\Model\ResourceModel\AddressUnit;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * TASK-TBM30R / AC-L7/L9 — suggester workfile: matcher suggestions become REVIEW_REQUIRED (never
 * APPROVED), ambiguous carries candidates, unmapped stays UNRESOLVED; output is import-compatible.
 */
class MappingSuggesterTest extends TestCase
{
    private MappingMatcher&MockObject $matcher;

    private AddressUnit&MockObject $unitResource;

    private MappingSuggester $suggester;

    private string $outputFile;

    protected function setUp(): void
    {
        $this->matcher = $this->createMock(MappingMatcher::class);
        $this->unitResource = $this->createMock(AddressUnit::class);
        $this->suggester = new MappingSuggester(
            $this->matcher,
            $this->unitResource,
            new CsvReader(),
            $this->createMock(VnAddressUnitProviderInterface::class)
        );
        $this->outputFile = sys_get_temp_dir() . '/ghn_suggest_' . uniqid() . '.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->outputFile);
        @unlink(dirname($this->outputFile) . '/GHN_ADDRESS_MAPPING_REVIEW_VN_ADMIN_2025.csv');
    }

    public function testWorkfileNeverContainsApprovedStatus(): void
    {
        $this->unitResource->method('fetchByScheme')->willReturn([
            ['entity_id' => 1, 'provider_key' => '1', 'status' => 'ACTIVE'],
        ]);
        $this->matcher->method('match')->willReturn([
            'VN-01' => ['level' => 1, 'status' => MappingMatcher::STATUS_APPROVED, 'chosen_provider_key' => '1', 'method' => 'EXACT_NAME', 'candidates' => ['1']],
            'VN-02' => ['level' => 1, 'status' => MappingMatcher::STATUS_AMBIGUOUS, 'candidates' => ['1', '2'], 'chosen_provider_key' => null, 'method' => null],
            'VN-03' => ['level' => 1, 'status' => MappingMatcher::STATUS_UNMAPPED, 'candidates' => [], 'chosen_provider_key' => null, 'method' => null],
        ]);

        $report = $this->suggester->suggest('VN_ADMIN_2025', $this->outputFile);

        $this->assertSame(3, $report['total']);
        $this->assertSame(1, $report['review_required']);
        $this->assertSame(1, $report['ambiguous']);
        $this->assertSame(1, $report['unresolved']);

        $rows = (new CsvReader())->read($this->outputFile, MappingCsv::HEADER);
        $byUnit = array_column($rows, null, 'secomm_unit_code');

        // Suggestion is REVIEW_REQUIRED — even for an exact-name match (DEC-FEATFQWEQ3-002 §1).
        $this->assertSame('REVIEW_REQUIRED', $byUnit['VN-01']['mapping_status']);
        $this->assertSame('EXACT_NAME', $byUnit['VN-01']['mapping_method']);
        $this->assertSame('1', $byUnit['VN-01']['ghn_provider_key']);
        $this->assertSame('AMBIGUOUS', $byUnit['VN-02']['mapping_status']);
        $this->assertStringContainsString('1 | 2', $byUnit['VN-02']['note']);
        $this->assertSame('UNRESOLVED', $byUnit['VN-03']['mapping_status']);

        $this->assertStringNotContainsString('APPROVED', (string) file_get_contents($this->outputFile));
    }

    public function testEmptyMasterDataFailsLoud(): void
    {
        $this->unitResource->method('fetchByScheme')->willReturn([]);

        $this->expectException(LocalizedException::class);
        $this->suggester->suggest('VN_ADMIN_2025', $this->outputFile);
    }
}
