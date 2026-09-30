<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\CarrierCoverage\CarrierCoverageConfigAdapter;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;
use Secomm\ShippingCore\Model\ResourceModel\Zone\Collection;
use Secomm\ShippingCore\Model\ResourceModel\Zone\CollectionFactory;
use Secomm\ShippingCore\Ui\DataProvider\CoverageFormDataProvider;

/**
 * TASK-WY6WP5 — Shipping Coverage form data for the (type, code) target: create mode
 * (no target_code) returns no record (XML defaults + is_create=1 apply), unregistered
 * targets are refused, Configure (registered, unconfigured) preselects the target with
 * is_create=1, Edit (configured) loads the persisted values with is_create=0, and the
 * meta switches the target_code field to a read-only single-option field on edit.
 */
class CoverageFormDataProviderTest extends TestCase
{
    private CarrierCoverageConfigAdapter&MockObject $configAdapter;

    private RequestInterface&MockObject $request;

    protected function setUp(): void
    {
        $this->configAdapter = $this->createMock(CarrierCoverageConfigAdapter::class);
        $this->request = $this->createMock(RequestInterface::class);
    }

    /**
     * Factory whose create() yields a mocked (never-loaded) zone collection — the runtime
     * shape: the provider's collection only absorbs the framework's addFilter() call.
     */
    private function zoneCollectionFactory(): CollectionFactory
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function requestWith(array $params): RequestInterface&MockObject
    {
        $this->request->method('getParam')->willReturnCallback(
            static fn (string $key, ?string $default = null) => $params[$key] ?? $default
        );

        return $this->request;
    }

    private function registry(): CoverageTargetRegistry
    {
        return new CoverageTargetRegistry([
            'secomm_ghn' => ['type' => 'CARRIER', 'code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
        ]);
    }

    private function build(RequestInterface $request, array $meta = []): CoverageFormDataProvider
    {
        return new CoverageFormDataProvider(
            'secomm_shippingcore_coverage_form_data_source',
            'target_code',
            'target_code',
            $this->configAdapter,
            $this->registry(),
            $request,
            $this->zoneCollectionFactory(),
            $meta
        );
    }

    /** The runtime meta shape the form XML produces for the target_code field. */
    private function formMetaSkeleton(): array
    {
        return [
            'coverage' => [
                'children' => [
                    'target_code' => ['arguments' => ['data' => ['config' => []]]],
                ],
            ],
        ];
    }

    public function testCreateModeReturnsNoRecord(): void
    {
        $dataProvider = $this->build($this->requestWith(['target_type' => 'CARRIER']));

        $this->assertSame([], $dataProvider->getData());
    }

    public function testUnregisteredTargetReturnsNoRecord(): void
    {
        $dataProvider = $this->build($this->requestWith(['target_code' => 'not_registered', 'target_type' => 'CARRIER']));

        $this->assertSame([], $dataProvider->getData());
    }

    /** Configure flow — registered target WITHOUT explicit config: create semantics. */
    public function testUnconfiguredRegisteredTargetKeepsCreateSemantics(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);
        $this->configAdapter->method('load')->willReturn([
            'destination_scope' => '',
            'allowed_zone_codes' => [],
            'rate_source_mode' => '',
            'address_resolution_policy' => '',
        ]);

        $data = $this->build($this->requestWith(['target_code' => 'secomm_ghn', 'target_type' => 'CARRIER']))->getData();

        $record = $data['secomm_ghn'];
        $this->assertSame('1', $record['is_create']);
        $this->assertSame('secomm_ghn', $record['target_code']);
        $this->assertSame('CARRIER', $record['target_type']);
        $this->assertSame('ALL', $record['destination_scope']);
        $this->assertSame('CARRIER_WITH_FALLBACK', $record['rate_source_mode']);
        $this->assertSame('FALLBACK', $record['address_resolution_policy']);
    }

    /** Edit flow — configured target: persisted values + is_create=0 (duplicate guard off). */
    public function testConfiguredTargetLoadsPersistedValues(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->method('load')->willReturn([
            'destination_scope' => 'SELECTED_ZONES',
            'allowed_zone_codes' => ['HCM_INNER'],
            'rate_source_mode' => 'CARRIER_ONLY',
            'address_resolution_policy' => 'STRICT',
        ]);

        $data = $this->build($this->requestWith(['target_code' => 'secomm_ghn', 'target_type' => 'CARRIER']))->getData();

        $record = $data['secomm_ghn'];
        $this->assertSame('0', $record['is_create']);
        $this->assertSame('SELECTED_ZONES', $record['destination_scope']);
        $this->assertSame(['HCM_INNER'], $record['allowed_zone_codes']);
        $this->assertSame('CARRIER_ONLY', $record['rate_source_mode']);
        $this->assertSame('STRICT', $record['address_resolution_policy']);
        $this->assertSame('GHN (Giao Hàng Nhanh)', $record['target_label']);
    }

    /**
     * Integration defect regression (2026-09-22): `Magento\Ui\Component\Form::
     * getDataSourceData()` ALWAYS calls `addFilter()` on the form data provider before
     * `getData()` — the provider must absorb that through a (never-loaded) collection
     * instead of fataling on a null collection.
     */
    public function testFrameworkFormAddFilterDoesNotFatal(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);
        $this->configAdapter->method('load')->willReturn([
            'destination_scope' => 'ALL',
            'allowed_zone_codes' => [],
            'rate_source_mode' => 'CARRIER_WITH_FALLBACK',
            'address_resolution_policy' => 'FALLBACK',
        ]);

        $dataProvider = $this->build($this->requestWith(['target_code' => 'secomm_ghn', 'target_type' => 'CARRIER']));

        // The exact framework sequence: addFilter(primaryFieldName, request value) → getData().
        $dataProvider->addFilter(new Filter(
            ['field' => 'target_code', 'value' => 'secomm_ghn', 'condition_type' => 'eq']
        ));

        $data = $dataProvider->getData();

        $this->assertSame('ALL', $data['secomm_ghn']['destination_scope']);
    }

    /** Edit meta: target_code becomes read-only and offers exactly the current target. */
    public function testEditMetaDisablesTargetCodeWithSingleOption(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(true);

        $meta = $this->build(
            $this->requestWith(['target_code' => 'secomm_ghn', 'target_type' => 'CARRIER']),
            $this->formMetaSkeleton()
        )->getMeta();

        $config = $meta['coverage']['children']['target_code']['arguments']['data']['config'];
        $this->assertTrue($config['disabled']);
        $this->assertSame(
            [['value' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)']],
            $config['options']
        );
    }

    /** Create/Configure meta: the XML RegisteredCarrierOptions source stays in charge. */
    public function testCreateMetaLeavesTargetCodeOptionsUntouched(): void
    {
        $this->configAdapter->method('hasExplicitConfig')->willReturn(false);

        $meta = $this->build(
            $this->requestWith(['target_code' => 'secomm_ghn', 'target_type' => 'CARRIER']),
            $this->formMetaSkeleton()
        )->getMeta();

        $config = $meta['coverage']['children']['target_code']['arguments']['data']['config'];
        $this->assertArrayNotHasKey('disabled', $config);
        $this->assertArrayNotHasKey('options', $config);
    }

    public function testCreateModeMetaUntouched(): void
    {
        $meta = $this->build($this->requestWith(['target_type' => 'CARRIER']), $this->formMetaSkeleton())->getMeta();

        $config = $meta['coverage']['children']['target_code']['arguments']['data']['config'];
        $this->assertSame([], $config);
    }
}
