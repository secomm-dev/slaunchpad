<?php

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Test\Unit\Plugin;

use Launchpad\SnowdogMenu\Plugin\TemplateResolverPlugin;
use Launchpad\SnowdogMenu\ViewModel\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Snowdog\Menu\Model\TemplateResolver;

final class TemplateResolverPluginTest extends TestCase
{
    private Config&MockObject $config;
    private TemplateResolver&MockObject $subject;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->subject = $this->createMock(TemplateResolver::class);
    }

    public function testConfiguredMenuUsesLaunchpadTemplateNamespace(): void
    {
        $this->configureIdentifiers('desktop-menu', 'mobile-menu');
        $block = new \stdClass();

        self::assertSame(
            [$block, Config::DEFAULT_MENU_IDENTIFIER, 'menu.phtml', null],
            $this->plugin()->beforeGetMenuTemplate($this->subject, $block, 'mobile-menu', 'menu.phtml')
        );
    }

    public function testOtherMenuKeepsItsOwnTemplateNamespace(): void
    {
        $this->configureIdentifiers('desktop-menu', 'mobile-menu');
        $block = new \stdClass();

        self::assertSame(
            [$block, 'footer-menu', 'menu.phtml', 12],
            $this->plugin()->beforeGetMenuTemplate($this->subject, $block, 'footer-menu', 'menu.phtml', 12)
        );
    }

    private function configureIdentifiers(string $desktop, string $mobile): void
    {
        $this->config->method('getDesktopMenuIdentifier')->willReturn($desktop);
        $this->config->method('getMobileMenuIdentifier')->willReturn($mobile);
    }

    private function plugin(): TemplateResolverPlugin
    {
        return new TemplateResolverPlugin($this->config);
    }
}
