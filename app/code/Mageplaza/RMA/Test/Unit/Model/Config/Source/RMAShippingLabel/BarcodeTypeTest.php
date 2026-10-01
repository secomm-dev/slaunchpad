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
use Mageplaza\RMA\Model\Config\Source\RMAShippingLabel\BarcodeType;
use PHPUnit\Framework\TestCase;

/**
 * Class BarcodeTypeTest
 * @package Mageplaza\RMA\Test\Unit\Model\Config\Source\RMAShippingLabel
 */
class BarcodeTypeTest extends TestCase
{
    /**
     * @var BarcodeType
     */
    protected $model;

    protected function setUp()
    {
        $helper = new ObjectManager($this);

        $this->model = $helper->getObject(
            BarcodeType::class
        );
    }

    /**
     * Test to actions option array
     */
    public function testToOptionArray()
    {
        $expectResult = [
            [
                'value' => 1,
                'label' => __('Order Increment ID')
            ],
            [
                'value' => 2,
                'label' => __('RMA Increment ID')
            ]
        ];
        $actualResult = $this->model->toOptionArray();
        $this->assertEquals($expectResult, $actualResult);
    }
}
