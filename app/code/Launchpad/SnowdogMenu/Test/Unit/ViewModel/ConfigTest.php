<?php

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Test\Unit\ViewModel;

use Launchpad\SnowdogMenu\ViewModel\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    public function testDesktopIdentifierUsesConfiguredValue(): void
    {
        $this->expectConfigValue(Config::CONFIG_PATH_DESKTOP_MENU, ' desktop-menu ');

        self::assertSame('desktop-menu', $this->config()->getDesktopMenuIdentifier());
    }

    public function testDesktopIdentifierFallsBackToDefault(): void
    {
        $this->expectConfigValue(Config::CONFIG_PATH_DESKTOP_MENU, '');

        self::assertSame(Config::DEFAULT_MENU_IDENTIFIER, $this->config()->getDesktopMenuIdentifier());
    }

    public function testMobileIdentifierUsesConfiguredValue(): void
    {
        $this->expectConfigValue(Config::CONFIG_PATH_MOBILE_MENU, ' mobile-menu ');

        self::assertSame('mobile-menu', $this->config()->getMobileMenuIdentifier());
    }

    public function testEmptyMobileIdentifierUsesDesktopValue(): void
    {
        $this->scopeConfig->expects(self::exactly(2))
            ->method('getValue')
            ->willReturnMap([
                [Config::CONFIG_PATH_MOBILE_MENU, ScopeInterface::SCOPE_STORE, null, ''],
                [Config::CONFIG_PATH_DESKTOP_MENU, ScopeInterface::SCOPE_STORE, null, 'shared-menu'],
            ]);

        self::assertSame('shared-menu', $this->config()->getMobileMenuIdentifier());
    }

    private function expectConfigValue(string $path, string $value): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('getValue')
            ->with($path, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($value);
    }

    private function config(): Config
    {
        return new Config($this->scopeConfig);
    }
}
