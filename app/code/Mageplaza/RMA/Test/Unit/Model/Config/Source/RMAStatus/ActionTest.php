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

namespace Mageplaza\RMA\Test\Unit\Model\Config\Source\RMAStatus;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mageplaza\RMA\Model\Config\Source\RMAStatus\Action;
use PHPUnit\Framework\TestCase;

/**
 * Class ActionTest
 * @package Mageplaza\RMA\Test\Unit\Model\Config\Source\RMAStatus
 */
class ActionTest extends TestCase
{
    /**
     * @var Action
     */
    protected $model;

    protected function setUp()
    {
        $helper = new ObjectManager($this);

        $this->model = $helper->getObject(
            Action::class
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
                'value' => 1,
                'label' => __('Create New Credit Memo')
            ],
            [
                'value' => 2,
                'label' => __('Reorder (Replace return Product by create new order)')
            ],
            [
                'value' => 3,
                'label' => __('Add Shipping Label')
            ]
        ];
        $actualResult = $this->model->toOptionArray();
        $this->assertEquals($expectResult, $actualResult);
    }
}
