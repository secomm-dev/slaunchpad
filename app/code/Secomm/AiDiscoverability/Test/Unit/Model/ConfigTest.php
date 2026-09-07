<?php
declare(strict_types=1);

namespace Secomm\AiDiscoverability\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\AiDiscoverability\Model\Config;

/**
 * Covers site-title fallback chain and deterministic currency read (SPEC-TASK-0X552E §12.2, §12.4).
 */
class ConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * @var Config
     */
    private $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testSiteTitlePrefersModuleConfig(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            function (string $path) {
                return $path === 'seocomm_ai_discoverability/general/site_title' ? '  Public Brand ' : null;
            }
        );

        $this->assertSame('Public Brand', $this->config->getSiteTitle(1));
    }

    public function testSiteTitleFallsBackToStoreInformationName(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            function (string $path) {
                return $path === 'general/store_information/name' ? 'Store Info Name' : null;
            }
        );

        $this->assertSame('Store Info Name', $this->config->getSiteTitle(1));
    }

    public function testSiteTitleEmptyWhenUnconfigured(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame('', $this->config->getSiteTitle(1));
    }

    public function testCurrencyCodeReadsStoreScopedDefaultCurrency(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('currency/options/default', ScopeInterface::SCOPE_STORE, 2)
            ->willReturn(' VND ');

        $this->assertSame('VND', $this->config->getCurrencyCode(2));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sectionTitleProvider(): array
    {
        return [
            'unconfigured falls back to default' => [null, 'Machine-readable Commerce'],
            'empty falls back to default' => ['', 'Machine-readable Commerce'],
            'whitespace falls back to default' => ['   ', 'Machine-readable Commerce'],
            'custom store-view title wins' => [' Structured Catalog ', 'Structured Catalog'],
        ];
    }

    /**
     * @dataProvider sectionTitleProvider
     */
    public function testSectionTitleResolution(?string $configured, string $expected): void
    {
        $this->scopeConfig->method('getValue')->willReturn($configured);

        $this->assertSame($expected, $this->config->getSectionTitle('machine_commerce', 1));
    }

    public function testEverySectionTitleKeyResolvesToANonEmptyDefault(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        foreach (Config::SECTION_TITLES as $key => $default) {
            $this->assertNotSame('', $this->config->getSectionTitle($key, 1), "key $key");
            $this->assertSame($default, $this->config->getSectionTitle($key, 1));
        }
    }
}
