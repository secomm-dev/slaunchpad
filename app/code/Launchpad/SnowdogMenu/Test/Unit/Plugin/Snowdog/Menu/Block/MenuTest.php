<?php

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Test\Unit\Plugin\Snowdog\Menu\Block;

use Launchpad\SnowdogMenu\Plugin\Snowdog\Menu\Block\Menu as MenuPlugin;
use Launchpad\SnowdogMenu\ViewModel\Config;
use Magento\Catalog\Model\Category;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Snowdog\Menu\Block\Menu as SnowdogMenuBlock;

final class MenuTest extends TestCase
{
    private Config&MockObject $config;
    private Registry&MockObject $registry;
    private SnowdogMenuBlock&MockObject $subject;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->registry = $this->createMock(Registry::class);
        $this->subject = $this->createMock(SnowdogMenuBlock::class);
    }

    public function testCurrentCategoryIdIsAddedToCacheKey(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(42);
        $this->registry->method('registry')->with('current_category')->willReturn($category);

        self::assertSame(
            ['snowmenu', 'current_category_42'],
            $this->plugin()->afterGetCacheKeyInfo($this->subject, ['snowmenu'])
        );
    }

    public function testNonCategoryPageKeepsOriginalCacheKey(): void
    {
        $this->registry->method('registry')->with('current_category')->willReturn(null);

        self::assertSame(
            ['snowmenu'],
            $this->plugin()->afterGetCacheKeyInfo($this->subject, ['snowmenu'])
        );
    }

    private function plugin(): MenuPlugin
    {
        return new MenuPlugin($this->config, $this->registry);
    }
}
