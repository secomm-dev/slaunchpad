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
                    'purpose' => 'Store metadata: store code, locale, currency and base URL.',
                ],
                [
                    'label' => 'Product Search',
                    'url' => 'https://example.com/ai/catalog/search?store=vietnam',
                    'purpose' => 'Search public products using the bounded AI Commerce catalog facade.',
                    // phpcs:disable Generic.Files.LineLength
                    'usage' => [
                        'Query parameters (all optional, combinable):',
                        '- q=KEYWORD: keyword search; omit q to browse the bounded catalog listing.',
                        '- category=CATEGORY_ID: one category id from the Categories endpoint; an unknown id returns 400 invalid_parameter.',
                        '- price_min=NUMBER / price_max=NUMBER: price bounds in store currency; combined both bounds apply; each works alone.',
                        '- sort=relevance|price_asc|price_desc|name_asc|name_desc: result ordering; relevance when omitted.',
                        '- price_asc/price_desc order by the catalog price index, which can differ from the displayed price (special/link pricing).',
                        '- filter[ATTRIBUTE]=OPTION_ID: allowlisted attributes only: color, size. OPTION_ID is a catalog attribute option id; text values may match nothing.',
                        '- page=NUMBER / page_size=NUMBER: 1-based pagination, defaults 1/20, page_size up to 50.',
                        '- store=STORE_CODE: read a specific store view; omit for the default store view; unknown code returns 400 invalid_store.',
                        '- Unknown parameter names (camelCase pageSize included) return 400 invalid_parameter.',
                        'Example: GET https://example.com/ai/catalog/search?store=vietnam&q=KEYWORD&sort=price_asc',
                        'Example: GET https://example.com/ai/catalog/search?store=vietnam&category=CATEGORY_ID'
                            . '&filter[ATTRIBUTE]=OPTION_ID&price_min=NUMBER&price_max=NUMBER&page=2&page_size=20',
                    ],
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

    /**
     * BUG-D4QK1Q §17: a store view with its own endpoint_path (here store id
     * 3, path "agent") must be advertised with ITS store-scoped base path and
     * ITS store code — never the default path under a store override.
     */
    public function testAdvertisesConfiguredBasePath(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        // Base path read must be scoped to the TARGET store (id 3), not default.
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path, string $scope = 'store', ?int $storeId = null): ?string => 'agent'
        );
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $entries = $source->getEntries($this->store);

        $this->assertSame('https://example.com/agent/store?store=vietnam', $entries[0]['url']);
        $this->assertSame('https://example.com/agent/products/{sku}?store=vietnam', $entries[3]['url']);
        // Usage examples follow the configured store-scoped base path.
        $this->assertStringContainsString(
            'GET https://example.com/agent/catalog/search?store=vietnam&q=KEYWORD',
            $entries[1]['usage'][10]
        );
        // Every URL carries the store selector and NONE carries the default /ai/ prefix.
        foreach ($entries as $entry) {
            $this->assertStringContainsString('store=vietnam', $entry['url']);
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

    /**
     * The Product Search usage guidance must mirror the LIVE-proven contract:
     * allowlist + page-size bound read from config (never hardcoded values),
     * examples built from the effective endpoint URL.
     */
    public function testUsageAdvertisesConfiguredAllowlistAndBound(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path, string $scope = 'store', ?int $storeId = null): ?string => match ($path) {
                'seocomm_ai_commerce/general/endpoint_path' => null,
                'seocomm_ai_commerce/general/filter_allowlist' => ' Brand , material ',
                'seocomm_ai_commerce/general/max_page_size' => '30',
                default => null,
            }
        );
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $usage = $source->getEntries($this->store)[1]['usage'];

        $this->assertStringContainsString('brand, material', $usage[6]);
        $this->assertStringContainsString('up to 30', $usage[7]);
    }

    public function testUsageOmitsFilterLineWhenAllowlistEmpty(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path, string $scope = 'store', ?int $storeId = null): ?string => match ($path) {
                'seocomm_ai_commerce/general/filter_allowlist' => '',
                'seocomm_ai_commerce/general/max_page_size' => '100',
                default => null,
            }
        );
        $source = new CommerceEndpointsSource($this->moduleList, $this->scopeConfig, $this->config);

        $usage = $source->getEntries($this->store)[1]['usage'];

        $this->assertCount(11, $usage);
        foreach ($usage as $line) {
            $this->assertStringNotContainsString('filter[', $line);
        }
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
