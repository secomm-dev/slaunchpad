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

namespace Mageplaza\Osc\Test\Unit\Block\Adminhtml\Plugin;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;
use PHPUnit\Framework\TestCase;

class OrderViewTabInfoTest extends TestCase
{
    /**
     * Check function getGiftOptionsHtml is exist
     */
    public function testAfterGetGiftOptionsHtml()
    {
        $objectManagerHelper = new ObjectManager($this);
        /**
         * @var Info $saleTabInfo
         */
        $saleTabInfo = $objectManagerHelper->getObject(Info::class);

        $this->assertTrue(method_exists($saleTabInfo, 'getGiftOptionsHtml'));
    }
}
