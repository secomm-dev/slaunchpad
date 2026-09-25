<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Test\Unit\Plugin\Checkout;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\DataStorage;
use Secomm\AddressDropdown\Model\Resolver\GetListCityGraphql;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;
use Secomm\AddressDropdown\Plugin\Checkout\DefaultConfigProviderPlugin;

/**
 * TASK-SEC-A5 — the master switch: the storefront JS flag is exposed store-scoped, and a
 * disabled switch stops the GraphQL surface from serving any AddressDropdown data.
 */
class DefaultConfigProviderPluginTest extends TestCase
{
    private Data&MockObject $helper;

    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(Data::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    public function testFlagExposedWhenEnabled(): void
    {
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(true);

        $result = $this->plugin()->afterGetConfig(
            $this->createMock(DefaultConfigProvider::class),
            []
        );

        $this->assertTrue($result['secommAddressDropdownEnabled']);
    }

    public function testFlagExposedWhenDisabled(): void
    {
        $this->helper->method('isAddressDropdownModuleEnabled')->willReturn(false);

        $result = $this->plugin()->afterGetConfig(
            $this->createMock(DefaultConfigProvider::class),
            []
        );

        $this->assertFalse($result['secommAddressDropdownEnabled']);
    }

    public function testDisabledSwitchServesNoGraphQLData(): void
    {
        // The switch is OFF: the resolver must return an empty result WITHOUT touching the
        // collection storage (no queries, no city payload — native address fields only).
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $collectionFactory = $this->createMock(CityLocaleCollectionFactory::class);
        $collectionFactory->expects($this->never())->method('create');

        $resolver = new GetListCityGraphql(
            $collectionFactory,
            $this->createMock(DataStorage::class),
            $this->scopeConfig
        );

        $this->assertSame([], $resolver->resolve(
            $this->createMock(\Magento\Framework\GraphQl\Config\Element\Field::class),
            $this->createMock(\Magento\Framework\GraphQl\Query\Resolver\ContextInterface::class),
            $this->createMock(\Magento\Framework\GraphQl\Schema\Type\ResolveInfo::class),
            null,
            ['input' => ['region_id' => 5]]
        ));
    }

    private function plugin(): DefaultConfigProviderPlugin
    {
        return new DefaultConfigProviderPlugin($this->helper);
    }
}
