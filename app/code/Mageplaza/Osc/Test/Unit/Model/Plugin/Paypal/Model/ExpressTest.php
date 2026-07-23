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

namespace Mageplaza\Osc\Test\Unit\Model\Plugin\Paypal\Model;

use Magento\Framework\DataObject;
use Magento\Paypal\Model\Express;
use Magento\Quote\Api\Data\PaymentInterface;
use Mageplaza\Osc\Model\Plugin\Paypal\Model\Express as PluginExpress;
use PHPUnit\Framework\TestCase;

class ExpressTest extends TestCase
{
    /**
     * @var PluginExpress
     */
    protected $plugin;

    protected function setUp(): void
    {
        $this->plugin = new PluginExpress();
    }

    public function testMethod()
    {
        $methods = get_class_methods(Express::class);

        $this->assertTrue(in_array('assignData', $methods));
    }

    public function testBeforeAssignData()
    {
        /**
         * @var Express $subject
         */
        $subject = $this->getMockBuilder(Express::class)->disableOriginalConstructor()->getMock();

        /**
         * @var DataObject $dataObjectMock
         */
        $dataObjectMock = $this->getMockBuilder(DataObject::class)->disableOriginalConstructor()->getMock();
        $additionalData = [
            'method' => 'checkmo',
            'po_number' => null,
            'extension_attributes' => [],
            'checks' => ['checkout', 'country', 'currency', 'total']
        ];

        $dataObjectMock->expects($this->once())->method('getData')
            ->with(PaymentInterface::KEY_ADDITIONAL_DATA)
            ->willReturn($additionalData);
        unset($additionalData['extension_attributes']);
        $dataObjectMock->expects($this->once())->method('setData')
            ->with(
                PaymentInterface::KEY_ADDITIONAL_DATA,
                $additionalData
            );

        $this->assertEquals([$dataObjectMock], $this->plugin->beforeAssignData($subject, $dataObjectMock));
    }
}
