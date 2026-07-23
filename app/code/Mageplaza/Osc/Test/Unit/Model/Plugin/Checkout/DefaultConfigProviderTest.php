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
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Checkout;

use Magento\Checkout\Model\DefaultConfigProvider as CoreDefaultConfigProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Mageplaza\Osc\Helper\Item;
use Mageplaza\Osc\Model\Plugin\Checkout\DefaultConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DefaultConfigProviderTest extends TestCase
{
    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var Item|MockObject
     */
    private $itemHelperMock;

    /**
     * @var DefaultConfigProvider
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()->getMock();

        $this->itemHelperMock = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->getMock();

        $this->plugin = new DefaultConfigProvider(
            $this->checkoutSessionMock,
            $this->itemHelperMock
        );
    }

    public function testMethod()
    {
        $methods = get_class_methods(CoreDefaultConfigProvider::class);

        $this->assertTrue(in_array('getConfig', $methods));
    }

    public function testAfterGetConfigWithInvalidOscPage()
    {
        /**
         * @var CoreDefaultConfigProvider $subjectMock
         */
        $subjectMock = $this->getMockBuilder(CoreDefaultConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->itemHelperMock->expects($this->once())->method('isOscPage')->willReturn(false);

        $this->assertEquals([], $this->plugin->afterGetConfig($subjectMock, []));
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testAfterGetConfig()
    {
        /**
         * @var CoreDefaultConfigProvider $subjectMock
         */
        $subjectMock = $this->getMockBuilder(CoreDefaultConfigProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->itemHelperMock->expects($this->once())->method('isOscPage')->willReturn(true);
        $quoteMock = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->checkoutSessionMock->expects($this->once())->method('getQuote')->willReturn($quoteMock);
        $this->itemHelperMock->expects($this->once())
            ->method('getItemOptionsConfig')
            ->with($quoteMock, 1)
            ->willReturn('test');
        $config = [
            'quoteItemData' => [
                [
                    'item_id' => 1
                ]
            ]
        ];

        $result = [
            'quoteItemData' => [
                [
                    'item_id' => 1,
                    'mposc' => 'test'
                ]
            ]
        ];

        $this->assertEquals($result, $this->plugin->afterGetConfig($subjectMock, $config));
    }
}
