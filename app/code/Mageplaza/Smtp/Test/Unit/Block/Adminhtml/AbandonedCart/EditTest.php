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

namespace Mageplaza\Smtp\Test\Unit\Block\Adminhtml\AbandonedCart;

use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Mageplaza\Smtp\Block\Adminhtml\AbandonedCart\Edit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Edit::class)]
class EditTest extends TestCase
{
    private const BACK_URL = 'https://admin.example.com/adminhtml/smtp/abandonedcart';

    private ?ObjectManagerInterface $originalObjectManager = null;

    private array $addedButtons = [];

    private array $removedButtons = [];

    protected function setUp(): void
    {
        try {
            $this->originalObjectManager = ObjectManager::getInstance();
        } catch (RuntimeException $e) {
            $this->originalObjectManager = null;
        }

        // Widget\Container::__construct() forwards only ($context, $data) to
        // Backend\Block\Template::__construct(), so JsonHelper/DirectoryHelper are always
        // resolved via ObjectManager::getInstance() (vendor/magento/module-backend/Block/Template.php:82-84).
        $jsonHelper = $this->createMock(JsonHelper::class);
        $directoryHelper = $this->createMock(DirectoryHelper::class);
        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        $objectManagerMock->method('get')->willReturnCallback(
            static fn (string $class) => match ($class) {
                JsonHelper::class => $jsonHelper,
                DirectoryHelper::class => $directoryHelper,
                default => null,
            }
        );
        ObjectManager::setInstance($objectManagerMock);
    }

    protected function tearDown(): void
    {
        if ($this->originalObjectManager !== null) {
            ObjectManager::setInstance($this->originalObjectManager);
        }

        parent::tearDown();
    }


    public function testConstructRemovesResetDeleteAndSaveButtons(): void
    {
        $this->createSut(null);

        $this->assertSame(['reset', 'delete', 'save'], $this->removedButtons);
    }

    public function testConstructAddsSendButtonWhenQuoteIsActiveParamTruthy(): void
    {
        $this->createSut('1');

        $this->assertArrayHasKey('send', $this->addedButtons);
        $this->assertSame(1, $this->addedButtons['send']['level']);
        $this->assertSame('primary', $this->addedButtons['send']['data']['class']);
        $this->assertEquals(__('Send Email'), $this->addedButtons['send']['data']['label']);
    }

    public function testConstructDoesNotAddSendButtonWhenQuoteIsActiveParamFalsy(): void
    {
        $this->createSut(null);

        $this->assertArrayNotHasKey('send', $this->addedButtons);
    }


    public function testGetBackUrlReturnsUrlBuilderResult(): void
    {
        $sut = $this->createSut(null);

        $this->assertSame(self::BACK_URL, $sut->getBackUrl());
    }

    private function createSut(mixed $quoteIsActive): Edit
    {
        $this->addedButtons = [];
        $this->removedButtons = [];

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['id', null, null],
            ['quote_is_active', null, $quoteIsActive],
        ]);

        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturn(self::BACK_URL);

        $buttonList = $this->createMock(ButtonList::class);
        $buttonList->method('add')->willReturnCallback(
            function (string $buttonId, array $data, int $level = 0): void {
                $this->addedButtons[$buttonId] = ['data' => $data, 'level' => $level];
            }
        );
        $buttonList->method('remove')->willReturnCallback(
            function (string $buttonId): void {
                $this->removedButtons[] = $buttonId;
            }
        );

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);
        $context->method('getButtonList')->willReturn($buttonList);

        return new Edit($context, [], $this->createMock(SecureHtmlRenderer::class));
    }
}
