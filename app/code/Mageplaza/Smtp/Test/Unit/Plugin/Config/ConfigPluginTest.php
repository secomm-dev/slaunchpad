<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Plugin\Config;

use Magento\Config\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Mageplaza\Smtp\Plugin\Config\ConfigPlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigPlugin::class)]
class ConfigPluginTest extends TestCase
{
    use MockCreationTrait;

    private RequestInterface&MockObject $request;
    private ManagerInterface&MockObject $messageManager;
    private ConfigPlugin $plugin;

    protected function setUp(): void
    {
        $this->request        = $this->createMock(RequestInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->plugin         = new ConfigPlugin($this->request, $this->messageManager);
    }

    private function createConfigMock(): Config&MockObject
    {
        return $this->createPartialMockWithReflection(
            Config::class,
            ['getSection', 'getGroups', 'setGroups']
        );
    }

    private function smtpGroups(string $protocol, string $port): array
    {
        return [
            'configuration_option' => [
                'fields' => [
                    'protocol' => ['value' => $protocol],
                    'port'     => ['value' => $port],
                ],
            ],
        ];
    }


    public function testBeforeSaveIgnoresNonSmtpSection(): void
    {
        $config = $this->createConfigMock();
        $config->method('getSection')->willReturn('general');
        $config->expects($this->never())->method('getGroups');
        $config->expects($this->never())->method('setGroups');
        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $result = $this->plugin->beforeSave($config);

        $this->assertSame([], $result);
    }

    public function testBeforeSaveReturnsEmptyArrayWhenGroupsHaveNoProtocolField(): void
    {
        $config = $this->createConfigMock();
        $config->method('getSection')->willReturn('smtp');
        $config->method('getGroups')->willReturn([]);
        $config->expects($this->never())->method('setGroups');
        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $result = $this->plugin->beforeSave($config);

        $this->assertSame([], $result);
    }

    public function testBeforeSaveRewritesPortAndAddsNoticeWhenTlsWithSslOnlyPort(): void
    {
        $config = $this->createConfigMock();
        $config->method('getSection')->willReturn('smtp');
        $config->method('getGroups')->willReturn($this->smtpGroups('tls', '465'));
        $config->expects($this->once())->method('setGroups')->with(
            $this->callback(
                static fn (array $groups): bool =>
                    $groups['configuration_option']['fields']['port']['value'] === '587'
            )
        );
        $this->messageManager->expects($this->once())->method('addNoticeMessage');

        $result = $this->plugin->beforeSave($config);

        $this->assertSame([], $result);
    }

    public function testBeforeSaveSkipsRewriteWhenTlsPortAlreadyCorrect(): void
    {
        $config = $this->createConfigMock();
        $config->method('getSection')->willReturn('smtp');
        $config->method('getGroups')->willReturn($this->smtpGroups('tls', '587'));
        $config->expects($this->never())->method('setGroups');
        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $result = $this->plugin->beforeSave($config);

        $this->assertSame([], $result);
    }

    public function testBeforeSaveSkipsRewriteWhenProtocolIsNotTls(): void
    {
        $config = $this->createConfigMock();
        $config->method('getSection')->willReturn('smtp');
        $config->method('getGroups')->willReturn($this->smtpGroups('ssl', '465'));
        $config->expects($this->never())->method('setGroups');
        $this->messageManager->expects($this->never())->method('addNoticeMessage');

        $result = $this->plugin->beforeSave($config);

        $this->assertSame([], $result);
    }
}
