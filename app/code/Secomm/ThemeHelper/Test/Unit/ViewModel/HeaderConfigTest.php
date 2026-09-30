<?php

declare(strict_types=1);

namespace Secomm\ThemeHelper\Test\Unit\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ThemeHelper\ViewModel\HeaderConfig;

final class HeaderConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    /**
     * @dataProvider stickyConfigProvider
     */
    public function testStickySettingUsesStoreScope(bool $configuredValue): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('secomm_theme/header/sticky_enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn($configuredValue);

        self::assertSame($configuredValue, (new HeaderConfig($this->scopeConfig))->isStickyEnabled());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function stickyConfigProvider(): array
    {
        return [
            'enabled' => [true],
            'disabled' => [false],
        ];
    }
}
