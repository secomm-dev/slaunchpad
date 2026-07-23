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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Catalog\Product\View\Options;

use Magento\Catalog\Block\Product\View\Options\AbstractOptions as CatalogAbstractOptions;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutInterface;
use Mageplaza\Osc\Model\Plugin\Catalog\Product\View\Options\AbstractOptions;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount as InvokedCountMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * Class AbstractOptions
 *
 */
class AbstractOptionsTest extends TestCase
{
    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var AbstractOptions|MockObject
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);
        $this->plugin = new AbstractOptions($this->loggerMock);
    }

    public function testMethod()
    {
        $methods = get_class_methods(CatalogAbstractOptions::class);

        $this->assertTrue(in_array('getOption', $methods));
    }

    /**
     * @return array
     */
    public function providerTestBeforeGetOption()
    {
        return [
            [
                false,
                self::once(),
            ],
            [
                false,
                self::once(),
            ]
        ];
    }

    /**
     * @param boolean             $handlesResult
     * @param InvokedCountMatcher $handleExpect
     *
     * @dataProvider providerTestBeforeGetOption
     *
     * @throws ReflectionException
     */
    public function testBeforeGetOption($handlesResult, $handleExpect)
    {
        $methods = get_class_methods(CatalogAbstractOptions::class);

        /**
         * @var CatalogAbstractOptions $abstractOptionMock
         */
        $abstractOptionMock = $this->getMockForAbstractClass(
            CatalogAbstractOptions::class,
            [],
            '',
            false,
            false,
            true,
            $methods
        );

        /**
         * @var PHPUnit_Framework_MockObject_MockObject $layoutMock
         */
        $layoutMock = $this->getMockBuilder(LayoutInterface::class)
            ->setMethods(['addHandle'])
            ->getMockForAbstractClass();
        $updateMock = $this->getMockForAbstractClass(ProcessorInterface::class);
        $abstractOptionMock->expects($this->once())->method('getLayout')->willReturn($layoutMock);
        $layoutMock->expects($this->once())->method('getUpdate')->willReturn($updateMock);
        $updateMock->expects($this->once())->method('getHandles')->willReturn($handlesResult);
        $updateMock->expects($handleExpect)->method('addHandle')->with('default');

        $this->plugin->beforeGetOption($abstractOptionMock);
    }

    public function testBeforeGetOptionWithException()
    {
        $methods = get_class_methods(CatalogAbstractOptions::class);
        /**
         * @var CatalogAbstractOptions $abstractOptionMock
         */
        $abstractOptionMock = $this->getMockForAbstractClass(
            CatalogAbstractOptions::class,
            [],
            '',
            false,
            false,
            true,
            $methods
        );

        $exception = new LocalizedException(__('test'));
        $abstractOptionMock->expects($this->once())->method('getLayout')->willThrowException($exception);
        $this->loggerMock->expects($this->once())->method('critical')->willReturn($exception);

        $this->plugin->beforeGetOption($abstractOptionMock);
    }
}
