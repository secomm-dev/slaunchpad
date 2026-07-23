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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Quote;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item;
use Mageplaza\Osc\Model\Plugin\Quote\Processor;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{
    /**
     * @var StockStateInterface
     */
    protected $stockStateMock;

    /**
     * @var Processor
     */
    protected $plugin;

    protected function setUp(): void
    {
        $this->stockStateMock = $this->getMockForAbstractClass(StockStateInterface::class);

        $this->plugin = new Processor($this->stockStateMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(Item\Processor::class);

        $this->assertTrue(in_array('prepare', $methods));
    }

    public function testAroundPrepare()
    {
        $productId = 1;
        $stockQty = 1;
        $cartQty = 1;
        $customPrice = 10;

        /**
         * @var Item\Processor $subject
         */
        $subject = $this->getMockBuilder(Item\Processor::class)->disableOriginalConstructor()->getMock();

        /**
         * @var Product $candidateMock
         */
        $candidateMock = $this->getMockBuilder(Product::class)
            ->setMethods(['getCartQty', 'getId', 'getStickWithinParent'])
            ->disableOriginalConstructor()->getMock();
        $itemMethods = get_class_methods(Item::class);
        $itemMethods[] = 'getId';

        /**
         * @var Item $itemMock
         */
        $itemMock = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()->getMock();
        $candidateMock->expects($this->once())->method('getId')->willReturn($productId);
        $this->stockStateMock->expects($this->once())->method('getStockQty')->with($productId)->willReturn($stockQty);
        $candidateMock->expects($this->exactly(2))->method('getCartQty')->willReturn($cartQty);

        /**
         * @var DataObject $dataObjectMock
         */
        $dataObjectMock = $this->getMockBuilder(DataObject::class)
            ->setMethods(['getResetCount', 'getId', 'getCustomPrice'])
            ->disableOriginalConstructor()->getMock();
        $dataObjectMock->expects($this->once())->method('getResetCount')->willReturn(1);

        $candidateMock->expects($this->once())->method('getStickWithinParent')->willReturn(false);
        $itemMock->expects($this->once())->method('getId')->willReturn(1);
        $dataObjectMock->expects($this->once())->method('getId')->willReturn(1);
        $itemMock->expects($this->once())->method('setData')->with(CartItemInterface::KEY_QTY, 0);
        $itemMock->expects($this->once())->method('setQty')->with(1);
        $dataObjectMock->expects($this->once())->method('getCustomPrice')->willReturn($customPrice);

        $closureMock = function () use ($subject) {
            return $subject;
        };

        $this->plugin->aroundPrepare($subject, $closureMock, $itemMock, $dataObjectMock, $candidateMock);
    }
}
