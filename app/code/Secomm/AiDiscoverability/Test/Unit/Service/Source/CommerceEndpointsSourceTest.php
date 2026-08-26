<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Service\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Model\Config;
use Secomm\AiDiscoverability\Service\Source\CommerceEndpointsSource;

/**
 * SPEC-TASK-7FBHHC §2.1: the commerce discovery section is metadata only —
 * derived from module presence + one config flag, with store-scoped URLs.
 * Endpoint URLs follow the configured AiCommerce base path (default "ai"),
 * and labels follow the configurable section titles (defaults preserved).
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
     * @var Config|\PHPUnit\Framework\MockObject\MockObject
     */
    private $config;

    /**
     * @var Store|\PHPUnit\Framework\MockObject\MockObject
     */
    private $store;

    protected function setUp(): void
    {
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->store = $this->createMock(Store::class);

        $this->store->method('getId')->willReturn(3);
        $this->store->method('getCode')->willReturn('vietnam');
        $this->store->method('getBaseUrl')->willReturn('https://example.com/');

        $this->moduleList->method('has')->with('Secomm_AiCommerce')->willReturn(true);

        // Default titles (Config mock: unstubbed reads fall back to defaults).
        $this->config->method('getSectionTitle')->willReturnCallback(
            static fn (string $key, ?int $storeId = null): string => Config::SECTION_TITLES[$key]
        );
    }

    public function testEnabledAdvertisesFullReadOnlySurfaceAtDefaultPath(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn(null);
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $this->assertSame(
            [
                [
                    'label' => 'Store Information',
                    'url' => 'https://example.com/ai/store?store=vietnam',
                    'purpose' => 'Store metadata, locale, currency and supported public catalog context.',
                ],
                [
                    'label' => 'Product Search',
                    'url' => 'https://example.com/ai/catalog/search?store=vietnam',
                    'purpose' => 'Search public products using the bounded AI Commerce catalog facade.',
                ],
                [
                    'label' => 'Categories',
                    'url' => 'https://example.com/ai/categories?store=vietnam',
                    'purpose' => 'Browse public category data for this store view.',
                ],
                [
                    'label' => 'Product Detail',
                    'url' => 'https://example.com/ai/products/{sku}?store=vietnam',
                    'purpose' => 'Retrieve public product information for a known SKU.',
                    'plain' => true,
                ],
            ],
            $source->getEntries($this->store)
        );
    }

    public function testAdvertisesConfiguredBasePath(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn('agent');
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $entries = $source->getEntries($this->store);

        $this->assertSame('https://example.com/agent/store?store=vietnam', $entries[0]['url']);
        $this->assertSame('https://example.com/agent/products/{sku}?store=vietnam', $entries[3]['url']);
        // No advertised URL carries the retired default /ai/ prefix.
        foreach ($entries as $entry) {
            $this->assertStringNotContainsString('/ai/', $entry['url']);
        }
    }

    public function testCustomEndpointHeadingsAreUsed(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->config = $this->createMock(Config::class);
        $this->config->method('getSectionTitle')->willReturnCallback(
            static fn (string $key, ?int $storeId = null): string => $key === 'store_information'
                ? 'Store Info'
                : Config::SECTION_TITLES[$key]
        );
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $this->assertSame('Store Info', $source->getEntries($this->store)[0]['label']);
    }

    public function testDisabledFlagYieldsNoEntries(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $this->assertSame([], $source->getEntries($this->store));
    }

    public function testAbsentModuleYieldsNoEntriesAndIgnoresConfig(): void
    {
        $this->moduleList = $this->createMock(ModuleListInterface::class);
        $this->moduleList->method('has')->with('Secomm_AiCommerce')->willReturn(false);
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $this->assertSame([], $source->getEntries($this->store));
        $this->scopeConfig->expects($this->never())->method('isSetFlag');
    }
}
