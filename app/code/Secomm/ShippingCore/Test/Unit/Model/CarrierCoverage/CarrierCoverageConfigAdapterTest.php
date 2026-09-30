<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\CarrierCoverage;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CarrierCoverage\Validator;

/**
 * TASK-WY6WP5 — the adapter boundary between the CoverageTarget world and the FROZEN
 * `carriers/<code>/...` config paths: save writes the same generic paths (DEFAULT scope,
 * config cache cleaned, nothing persisted on invalid input); load returns raw DEFAULT
 * values; hasExplicitConfig reads PERSISTED rows of ANY scope straight from
 * core_config_data; reset removes only DEFAULT rows and reports the count;
 * nonDefaultScopeRows names the surviving scoped overrides.
 */
class CarrierCoverageConfigAdapterTest extends TestCase
{
    private const GHN = 'secomm_ghn';

    private WriterInterface&MockObject $writer;

    private TypeListInterface&MockObject $cacheTypeList;

    private AdapterInterface&MockObject $adapter;

    private StoreManagerInterface&MockObject $storeManager;

    private CoverageTargetIdentity $identity;

    protected function setUp(): void
    {
        $this->writer = $this->createMock(WriterInterface::class);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->adapter = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $this->adapter->method('select')->willReturn($select);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->identity = CoverageTargetIdentity::carrier(self::GHN);
    }

    private function build(ScopeConfigInterface $scopeConfig): CarrierCoverageConfigAdapter
    {
        $zoneRegistry = $this->createMock(CanonicalZoneRegistryInterface::class);
        $zoneRegistry->method('getByCode')->willReturnCallback(
            function (string $code): ?CanonicalZoneInterface {
                if (in_array($code, ['HCM_INNER', 'HN_INNER'], true)) {
                    return $this->createMock(CanonicalZoneInterface::class);
                }

                return null;
            }
        );
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);

        return new CarrierCoverageConfigAdapter(
            $scopeConfig,
            $this->writer,
            $this->cacheTypeList,
            new Validator($zoneRegistry),
            $resource,
            $this->storeManager
        );
    }

    private function paths(): array
    {
        return [
            "carriers/" . self::GHN . "/destination_scope",
            "carriers/" . self::GHN . "/allowed_zone_codes",
            "carriers/" . self::GHN . "/rate_source_mode",
            "carriers/" . self::GHN . "/address_resolution_policy",
        ];
    }

    public function testSaveWritesExistingGenericPathsAndCleansConfigCache(): void
    {
        $expected = [
            'carriers/secomm_ghn/destination_scope' => 'SELECTED_ZONES',
            'carriers/secomm_ghn/allowed_zone_codes' => 'HCM_INNER,HN_INNER',
            'carriers/secomm_ghn/rate_source_mode' => 'CARRIER_WITH_FALLBACK',
            'carriers/secomm_ghn/address_resolution_policy' => 'FALLBACK',
        ];
        $written = [];
        $this->writer->method('save')->willReturnCallback(
            function (string $path, string $value) use (&$written): void {
                $written[$path] = $value;
            }
        );
        $this->cacheTypeList->expects($this->once())->method('cleanType')->with('config');

        $codes = $this->build($this->createMock(ScopeConfigInterface::class))->save(
            $this->identity,
            'SELECTED_ZONES',
            ['HCM_INNER', 'HN_INNER'],
            'CARRIER_WITH_FALLBACK',
            'FALLBACK'
        );

        $this->assertSame(['HCM_INNER', 'HN_INNER'], $codes);
        $this->assertSame($expected, $written);
    }

    public function testInvalidSubmissionPersistsNothing(): void
    {
        $this->writer->expects($this->never())->method('save');
        $this->cacheTypeList->expects($this->never())->method('cleanType');

        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->build($this->createMock(ScopeConfigInterface::class))->save(
            $this->identity,
            'SELECTED_ZONES',
            ['GHOST_ZONE'],
            'CARRIER_ONLY',
            'STRICT'
        );
    }

    public function testLoadReturnsRawDefaultValues(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path): ?string {
                return match ($path) {
                    'carriers/secomm_ghn/destination_scope' => 'SELECTED_ZONES',
                    'carriers/secomm_ghn/allowed_zone_codes' => 'HCM_INNER, HN_INNER,',
                    'carriers/secomm_ghn/rate_source_mode' => null,
                    default => '',
                };
            }
        );

        $data = $this->build($scopeConfig)->load($this->identity);

        $this->assertSame('SELECTED_ZONES', $data['destination_scope']);
        $this->assertSame(['HCM_INNER', 'HN_INNER'], $data['allowed_zone_codes']);
        $this->assertSame('', $data['rate_source_mode']);
        $this->assertSame('', $data['address_resolution_policy']);
    }

    /** A target with zero persisted rows at any scope is Not Configured. */
    public function testHasExplicitConfigFalseWhenNoRowsExist(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn('0');

        $this->assertFalse($this->build($this->createMock(ScopeConfigInterface::class))->hasExplicitConfig($this->identity));
    }

    /** A WEBSITE/STORE-only config still counts — scoped rows are live for their stores. */
    public function testHasExplicitConfigTrueWhenAnyScopeRowExists(): void
    {
        $this->adapter->expects($this->once())->method('fetchOne')->willReturn('3');

        $adapter = $this->build($this->createMock(ScopeConfigInterface::class));
        $this->assertTrue($adapter->hasExplicitConfig($this->identity));
        // Memoized per identity — the second check must not re-query.
        $this->assertTrue($adapter->hasExplicitConfig($this->identity));
    }

    public function testResetRemovesOnlyDefaultRowsAndReportsCount(): void
    {
        // hasDefaultRow per path: destination_scope yes, zones no, mode yes, policy no.
        $this->adapter->method('fetchOne')->willReturnOnConsecutiveCalls('1', false, '1', false);
        $deleted = [];
        $this->writer->method('delete')->willReturnCallback(
            function (string $path, string $scope, int $scopeId) use (&$deleted): void {
                $deleted[] = [$path, $scope, $scopeId];
            }
        );
        $this->cacheTypeList->expects($this->once())->method('cleanType')->with('config');

        $removed = $this->build($this->createMock(ScopeConfigInterface::class))->reset($this->identity);

        $this->assertSame(2, $removed);
        $this->assertSame([
            [$this->paths()[0], 'default', 0],
            [$this->paths()[1], 'default', 0],
            [$this->paths()[2], 'default', 0],
            [$this->paths()[3], 'default', 0],
        ], $deleted);
    }

    public function testResetWithNothingConfiguredCleansNoCache(): void
    {
        $this->adapter->method('fetchOne')->willReturn(false);
        $this->writer->expects($this->exactly(4))->method('delete');
        $this->cacheTypeList->expects($this->never())->method('cleanType');

        $this->assertSame(0, $this->build($this->createMock(ScopeConfigInterface::class))->reset($this->identity));
    }

    public function testNonDefaultScopeRowsReturnLabeledDescriptions(): void
    {
        $this->adapter->method('fetchAll')->willReturn([
            ['scope' => 'websites', 'scope_id' => 1],
            ['scope' => 'stores', 'scope_id' => 2],
            ['scope' => 'websites', 'scope_id' => 1],
        ]);
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getName')->willReturn('Vietnam Store');
        $store = $this->createMock(StoreInterface::class);
        $store->method('getName')->willReturn('English');
        $this->storeManager->method('getWebsite')->willReturn($website);
        $this->storeManager->method('getStore')->willReturn($store);

        $rows = $this->build($this->createMock(ScopeConfigInterface::class))->nonDefaultScopeRows($this->identity);

        $this->assertSame(['Website: Vietnam Store', 'Store View: English'], $rows);
    }

    /** A scope id that no longer resolves still warns (id fallback — never dropped). */
    public function testNonDefaultScopeRowsSurviveDeletedScopeEntities(): void
    {
        $this->adapter->method('fetchAll')->willReturn([
            ['scope' => 'websites', 'scope_id' => 99],
        ]);
        $this->storeManager->method('getWebsite')->willThrowException(
            new \Magento\Framework\Exception\LocalizedException(new \Magento\Framework\Phrase('gone'))
        );

        $rows = $this->build($this->createMock(ScopeConfigInterface::class))->nonDefaultScopeRows($this->identity);

        $this->assertSame(['Websites #99'], $rows);
    }

    /** Save flips the explicit-config memo — a same-request re-check sees the save. */
    public function testSaveMarksExplicitConfigWithinRequest(): void
    {
        // hasExplicitConfig: initial check 0 rows; after save memoized true.
        $this->adapter->method('fetchOne')->willReturn('0');
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $adapter = $this->build($scopeConfig);
        $this->writer->method('save');

        $this->assertFalse($adapter->hasExplicitConfig($this->identity));
        $adapter->save($this->identity, 'ALL', [], 'CARRIER_ONLY', 'STRICT');
        $this->assertTrue($adapter->hasExplicitConfig($this->identity));
    }
}
