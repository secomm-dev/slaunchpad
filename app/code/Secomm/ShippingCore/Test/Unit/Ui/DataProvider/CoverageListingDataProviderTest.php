<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;
use Secomm\ShippingCore\Model\Config\Source\AvailabilityOptions;
use Secomm\ShippingCore\Model\ResourceModel\Zone\Collection;
use Secomm\ShippingCore\Model\ResourceModel\Zone\CollectionFactory;
use Secomm\ShippingCore\Ui\DataProvider\CoverageListingDataProvider;

/**
 * TASK-WY6WP5 — Shipping Coverage grid data (directive §30 A–C + P): one row per
 * REGISTERED CARRIER target only (unregistered carriers never appear — GHTK opt-in is
 * deliberately absent), "Not Configured" with the effective default availability when no
 * explicit config exists, never fabricating persisted config. Framework calls
 * (addFilter/setLimit) must not fatal on the collection-less provider.
 */
class CoverageListingDataProviderTest extends TestCase
{
    private CarrierCoverageConfigAdapter&MockObject $configAdapter;

    protected function setUp(): void
    {
        $this->configAdapter = $this->createMock(CarrierCoverageConfigAdapter::class);
    }

    /**
     * @param array<string, array<string, mixed>> $registrations
     */
    private function build(array $registrations): CoverageListingDataProvider
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new CoverageListingDataProvider(
            'secomm_shippingcore_coverage_listing_data_source',
            'target_key',
            'target_key',
            new CoverageTargetRegistry($registrations),
            $this->configAdapter,
            new AvailabilityOptions(),
            $factory
        );
    }

    private function ghnOnlyRegistrations(): array
    {
        return [
            'secomm_ghn' => ['type' => 'CARRIER', 'code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
        ];
    }

    /** A — the registered target appears with its identity + label. */
    public function testRegisteredCarrierTargetAppears(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);

        $data = $this->build($this->ghnOnlyRegistrations())->getData();

        $this->assertSame(1, $data['totalRecords']);
        $row = $data['items'][0];
        $this->assertSame('CARRIER:secomm_ghn', $row['target_key']);
        $this->assertSame('secomm_ghn', $row['target_code']);
        $this->assertSame('CARRIER', $row['target_type']);
        $this->assertSame('Carrier', $row['target_type_label']);
        $this->assertSame('GHN (Giao Hàng Nhanh)', $row['target_label']);
    }

    /** B — an unregistered carrier does not appear (no fabricated rows, no discovery). */
    public function testUnregisteredCarrierDoesNotAppear(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);

        $data = $this->build($this->ghnOnlyRegistrations())->getData();

        $codes = array_column($data['items'], 'target_code');
        $this->assertNotContains('ghtk', $codes);
        $this->assertNotContains('mptablerate', $codes);
        $this->assertNotContains('flatrate', $codes);
    }

    /** C — no explicit config → Not Configured + effective All Vietnam (documented default). */
    public function testNotConfiguredTargetShowsDefaults(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);

        $data = $this->build($this->ghnOnlyRegistrations())->getData();

        $row = $data['items'][0];
        $this->assertSame('not_configured', $row['configuration_status']);
        $this->assertSame('Not Configured', $row['configuration_status_label']);
        $this->assertSame('All Vietnam (default — not explicitly configured)', $row['availability_label']);
        $this->assertSame('—', $row['zones_label']);
        $this->assertSame('configure', $row['action_mode']);
    }

    /** Configured target: persisted status + labels + Edit action. */
    public function testConfiguredTargetShowsPersistedState(): void
    {
        $identity = \Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity::carrier('secomm_ghn');
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->method('load')->with($identity)->willReturn([
            'destination_scope' => 'SELECTED_ZONES',
            'allowed_zone_codes' => ['HCM_INNER', 'HN_INNER'],
            'rate_source_mode' => 'CARRIER_WITH_FALLBACK',
            'address_resolution_policy' => 'FALLBACK',
        ]);

        $data = $this->build($this->ghnOnlyRegistrations())->getData();

        $row = $data['items'][0];
        $this->assertSame('configured', $row['configuration_status']);
        $this->assertSame('Configured', $row['configuration_status_label']);
        $this->assertSame('Only Selected Zones', $row['availability_label']);
        $this->assertSame('HCM_INNER, HN_INNER', $row['zones_label']);
        $this->assertSame('edit', $row['action_mode']);
    }

    /** A long zone list is summarized (first three + remainder count). */
    public function testLongZoneListIsSummarized(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->method('load')->willReturn([
            'destination_scope' => 'ALL_EXCEPT_SELECTED_ZONES',
            'allowed_zone_codes' => ['A_ONE', 'B_TWO', 'C_THREE', 'D_FOUR', 'E_FIVE'],
            'rate_source_mode' => 'CARRIER_ONLY',
            'address_resolution_policy' => 'STRICT',
        ]);

        $data = $this->build($this->ghnOnlyRegistrations())->getData();

        $this->assertSame('A_ONE, B_TWO, C_THREE … +2', $data['items'][0]['zones_label']);
        $this->assertSame('All Except Selected Zones', $data['items'][0]['availability_label']);
    }

    /** P — the framework's filter/paging calls are absorbed (collection-less provider). */
    public function testFrameworkFilterAndPagingCallsDoNotFatal(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);
        $provider = $this->build($this->ghnOnlyRegistrations());

        $this->assertInstanceOf(AbstractDataProvider::class, $provider);
        $provider->addFilter(new Filter(['field' => 'target_key', 'value' => 'CARRIER:secomm_ghn', 'condition_type' => 'eq']));
        $provider->setLimit(0, 20);

        $this->assertSame(1, $provider->getData()['totalRecords']);
    }

    /** Multiple registered targets — deterministic registry order, every target a row. */
    public function testEveryRegisteredTargetGetsARow(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);

        $data = $this->build([
            'secomm_ghn' => ['type' => 'CARRIER', 'code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
            'ghtk' => ['type' => 'CARRIER', 'code' => 'ghtk', 'label' => 'GHTK'],
        ])->getData();

        $this->assertSame(2, $data['totalRecords']);
        $this->assertSame(['CARRIER:ghtk', 'CARRIER:secomm_ghn'], array_column($data['items'], 'target_key'));
    }
}
