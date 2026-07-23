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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Catalog\Product\View\Type\Bundle;

use Magento\Bundle\Block\Catalog\Product\View\Type\Bundle\Option;
use Magento\Bundle\Block\DataProviders\OptionPriceRenderer;
use Mageplaza\Osc\Helper\Data;
use Mageplaza\Osc\Model\Plugin\Catalog\Product\View\Type\Bundle\OptionPlugin;
use PHPUnit\Framework\TestCase;

/**
 * Class OptionPlugin
 *
 */
class OptionPluginTest extends TestCase
{
    /**
     * @var Data
     */
    private $helperMock;

    /**
     * @var OptionPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->helperMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()->getMock();
        $this->plugin = new OptionPlugin($this->helperMock);
    }

    public function testBeforeGetData()
    {
        /**
         * @param Option $optionMock
         */
        $optionMock = $this->getMockBuilder(Option::class)
            ->setMethods(['setTierPriceRenderer'])
            ->disableOriginalConstructor()->getMock();
        if (class_exists('Magento\Bundle\Block\DataProviders\OptionPriceRenderer')) {
            $optionPriceRendererMock = $this->getMockBuilder(OptionPriceRenderer::class)
                ->disableOriginalConstructor()
                ->getMock();
            $this->helperMock->expects($this->once())
                ->method('getObject')
                ->with('Magento\Bundle\Block\DataProviders\OptionPriceRenderer')
                ->willReturn($optionPriceRendererMock);
            $optionMock->expects($this->once())->method('setTierPriceRenderer')->with($optionPriceRendererMock);
        }

        $this->plugin->beforeGetData($optionMock);
    }
}
