<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\CarrierCoverage;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierZoneIndex;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 (final TL verification) — the reference index reads PERSISTED
 * rows per config scope (core_config_data), never ScopeConfig effective resolution:
 * A. DEFAULT reference found; B. WEBSITE-only reference found; C. STORE-only reference
 * found; D. unreferenced in every scope → no reference. Metadata carries carrier + scope
 * type + scope id + human scope label. Unregistered carrier paths are never indexed.
 */
class CarrierZoneIndexTest extends TestCase
{
    private ResourceConnection&MockObject $resource;

    private StoreManagerInterface&MockObject $storeManager;

    /** @var array<int, array{scope: string, scope_id: int, value: string, path: string}> */
    private array $rows = [];

    protected function setUp(): void
    {
        $this->rows = [];
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
    }

    private function build(): CarrierZoneIndex
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchAll')->willReturn($this->rows);
        $this->resource->method('getConnection')->willReturn($adapter);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn(1);
        $website->method('getName')->willReturn('Vietnam Store');
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $store->method('getName')->willReturn('English');
        $this->storeManager->method('getWebsites')->willReturn([1 => $website]);
        $this->storeManager->method('getStores')->willReturn([2 => $store]);

        return new CarrierZoneIndex(
            $this->resource,
            new CoverageTargetRegistry([
                'secomm_ghn' => ['type' => 'CARRIER', 'code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
                'other' => ['type' => 'CARRIER', 'code' => 'other', 'label' => 'Other Carrier'],
            ]),
            $this->storeManager
        );
    }

    private function row(string $scope, int $scopeId, string $path, string $value): void
    {
        $this->rows[] = ['scope' => $scope, 'scope_id' => $scopeId, 'value' => $value, 'path' => $path];
    }

    /** A — DEFAULT scope reference. */
    public function testDefaultReferenceIsFound(): void
    {
        $this->row('default', 0, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER, DN_INNER');

        $references = $this->build()->findReferences('HCM_INNER');

        $this->assertCount(1, $references);
        $this->assertSame('secomm_ghn', $references[0]['carrier']);
        $this->assertSame('GHN (Giao Hàng Nhanh)', $references[0]['carrier_label']);
        $this->assertSame('default', $references[0]['scope']);
        $this->assertSame('Default', $references[0]['scope_label']);
        $this->assertSame(['secomm_ghn'], $this->build()->carriersForZone('HCM_INNER'));
    }

    /** B — WEBSITE-only persisted reference (live for the website's stores at runtime). */
    public function testWebsiteOnlyReferenceIsFound(): void
    {
        $this->row('websites', 1, 'carriers/secomm_ghn/allowed_zone_codes', 'DN_INNER');

        $references = $this->build()->findReferences('DN_INNER');

        $this->assertCount(1, $references);
        $this->assertSame('websites', $references[0]['scope']);
        $this->assertSame(1, $references[0]['scope_id']);
        $this->assertSame('Website: Vietnam Store', $references[0]['scope_label']);
    }

    /** C — STORE-only persisted reference (live for that store at runtime). */
    public function testStoreOnlyReferenceIsFound(): void
    {
        $this->row('stores', 2, 'carriers/secomm_ghn/allowed_zone_codes', 'HN_INNER');

        $references = $this->build()->findReferences('HN_INNER');

        $this->assertCount(1, $references);
        $this->assertSame('stores', $references[0]['scope']);
        $this->assertSame('Store View: English', $references[0]['scope_label']);
    }

    /** D — unreferenced in every supported scope → deletable. */
    public function testUnreferencedInEveryScopeYieldsNothing(): void
    {
        $this->row('default', 0, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');
        $this->row('websites', 1, 'carriers/other/allowed_zone_codes', 'DN_INNER');
        $this->row('stores', 2, 'carriers/secomm_ghn/allowed_zone_codes', 'HN_INNER');

        $this->assertSame([], $this->build()->findReferences('NOWHERE'));
        $this->assertSame([], $this->build()->carriersForZone('NOWHERE'));
    }

    /** Same zone persisted at several layers → every layer reported (not merged away). */
    public function testLayeredReferencesAreAllReported(): void
    {
        $this->row('default', 0, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');
        $this->row('websites', 1, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');
        $this->row('stores', 2, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');

        $this->assertCount(3, $this->build()->findReferences('HCM_INNER'));
    }

    public function testUnregisteredCarrierPathIsNeverIndexed(): void
    {
        $this->row('default', 0, 'carriers/ghost_legacy/allowed_zone_codes', 'HCM_INNER');

        $this->assertSame([], $this->build()->findReferences('HCM_INNER'));
    }

    /** A malformed carrier code in a stray config row must not brick the zone admin. */
    public function testMalformedCarrierCodeInConfigRowIsSkipped(): void
    {
        $this->row('default', 0, 'carriers/Weird-Code!/allowed_zone_codes', 'HCM_INNER');
        $this->row('default', 0, 'carriers/5ecomm/allowed_zone_codes', 'HCM_INNER');

        $references = $this->build()->findReferences('HCM_INNER');

        $this->assertSame([], $references);
    }

    public function testDistinctCarriersOrderedByScopeLayer(): void
    {
        $this->row('default', 0, 'carriers/other/allowed_zone_codes', 'HCM_INNER');
        $this->row('websites', 1, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');

        // default-scope row precedes websites-scope row in the deterministic order.
        $this->assertSame(['other', 'secomm_ghn'], $this->build()->carriersForZone('HCM_INNER'));
    }

    public function testDeletedScopeIdFallsBackToScopeName(): void
    {
        $this->row('websites', 99, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');

        $references = $this->build()->findReferences('HCM_INNER');

        $this->assertSame('Websites', $references[0]['scope_label']);
    }

    public function testStoreManagerFailureKeepsGuardCorrect(): void
    {
        $this->row('websites', 1, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');
        $this->storeManager->method('getWebsites')->willThrowException(
            new LocalizedException(new Phrase('no stores'))
        );

        $references = $this->build()->findReferences('HCM_INNER');

        // The integrity guard must still see the persisted reference (id fallback label).
        $this->assertCount(1, $references);
        $this->assertSame('Websites', $references[0]['scope_label']);
    }

    public function testEmptyZoneCodeShortCircuits(): void
    {
        $this->row('default', 0, 'carriers/secomm_ghn/allowed_zone_codes', 'HCM_INNER');

        $this->assertSame([], $this->build()->findReferences('  '));
    }
}
