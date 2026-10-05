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
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Test\Unit\Model\Config\Source\RMAShippingLabel;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mageplaza\RMA\Model\Config\Source\RMAShippingLabel\Information;
use PHPUnit\Framework\TestCase;

/**
 * Class InformationTest
 * @package Mageplaza\RMA\Test\Unit\Model\Config\Source\RMAShippingLabel
 */
class InformationTest extends TestCase
{
    /**
     * @var Information
     */
    protected $model;

    protected function setUp()
    {
        $helper = new ObjectManager($this);

        $this->model = $helper->getObject(
            Information::class
        );
    }

    /**
     * Test to actions option array
     */
    public function testToOptionArray()
    {
        $expectResult = [
            [
                'value' => 0,
                'label' => __('-- Please Select --')
            ],
            [
                'value' => 'logo',
                'label' => __('Logo')
            ],
            [
                'value' => 'order_shipping_address',
                'label' => __('Order Shipping Address')
            ],
            [
                'value' => 'order_increment_id',
                'label' => __('Order Increment ID')
            ],
            [
                'value' => 'rma_increment_id',
                'label' => __('RMA Increment ID')
            ],
            [
                'value' => 'rma_information',
                'label' => __('RMA Information')
            ],
            [
                'value' => 'return_shipping_address',
                'label' => __('Return Shipping Address')
            ],
            [
                'value' => 'print_date',
                'label' => __('Print Date')
            ],
            [
                'value' => 'request_date',
                'label' => __('Request Date')
            ],
            [
                'value' => 'barcode',
                'label' => __('Barcode')
            ]
        ];
        $actualResult = $this->model->toOptionArray();
        $this->assertEquals($expectResult, $actualResult);
    }
}
