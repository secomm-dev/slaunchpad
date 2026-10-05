<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\EInvoiceCore\Model\Config;

/**
 * Unit tests for electronic invoice configuration reader.
 */
class ConfigTest extends TestCase
{
    /**
     * Scope configuration mock.
     *
     * @var ScopeConfigInterface&MockObject
     */
    private $scopeConfig;

    /**
     * System under test.
     *
     * @var Config
     */
    private $config;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    /**
     * @return void
     */
    public function testIsEnabledReadsScopeConfig(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, 'store', 1)
            ->willReturn(true);

        self::assertTrue($this->config->isEnabled(1));
    }

    /**
     * @return void
     */
    public function testIsAutoCreditmemoAdjustmentReadsScopeConfig(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(Config::XML_PATH_AUTO_CREDITMEMO_ADJUSTMENT, 'store', 1)
            ->willReturn(false);

        self::assertFalse($this->config->isAutoCreditmemoAdjustment(1));
    }

    /**
     * @return void
     */
    public function testGetProviderDefaultsToMisaWhenUnset(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_PROVIDER, 'store', null)
            ->willReturn('');

        self::assertSame(Config::PROVIDER_MISA, $this->config->getProvider());
    }

    /**
     * @return void
     */
    public function testGetProviderReadsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with(Config::XML_PATH_PROVIDER, 'store', 2)
            ->willReturn(Config::PROVIDER_MISA);

        self::assertSame(Config::PROVIDER_MISA, $this->config->getProvider(2));
    }
}
