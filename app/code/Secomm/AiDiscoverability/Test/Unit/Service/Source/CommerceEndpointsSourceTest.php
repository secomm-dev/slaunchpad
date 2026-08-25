<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Service\Source\CommerceEndpointsSource;

/**
 * SPEC-TASK-7FBHHC §2.1: the commerce discovery section is metadata only —
 * derived from module presence + one config flag, with store-scoped URLs.
 */
class CommerceEndpointsSourceTest extends TestCase
{
    /**
     * @var ModuleListInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $moduleList;

    /**
     * @var ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $scopeConfig;

    /**
     * @var Store|\PHPUnit\Framework\MockObject\MockObject
     */
    private $store;

    protected function setUp(): void
    {
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->store = $this->createMock(Store::class);

        $this->store->method('getId')->willReturn(3);
        $this->store->method('getCode')->willReturn('vietnam');
        $this->store->method('getBaseUrl')->willReturn('https://example.com/');

        $this->moduleList->method('has')->with('Secomm_AiCommerce')->willReturn(true);
    }

    public function testEnabledAdvertisesFullReadOnlySurface(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig);

        $this->assertSame(
            [
                ['label' => 'Store Information', 'url' => 'https://example.com/ai/store?store=vietnam'],
                ['label' => 'Product Search', 'url' => 'https://example.com/ai/catalog/search?store=vietnam'],
                ['label' => 'Categories', 'url' => 'https://example.com/ai/categories?store=vietnam'],
                [
                    'label' => 'Product Detail',
                    'url' => 'https://example.com/ai/products/{sku}?store=vietnam',
                    'plain' => true,
                ],
            ],
            $source->getEntries($this->store)
        );
    }

    public function testDisabledFlagYieldsNoEntries(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig);

        $this->assertSame([], $source->getEntries($this->store));
    }

    public function testAbsentModuleYieldsNoEntriesAndIgnoresConfig(): void
    {
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->moduleList->method('has')->with('Secomm_AiCommerce')->willReturn(false);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig);

        $this->assertSame([], $source->getEntries($this->store));
        $this->scopeConfig->expects($this->never())->method('isSetFlag');
    }
}
