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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Adminhtml\ShippingLabel\Edit;

use Magento\Backend\Block\Widget\Tabs as WidgetTabs;

/**
 * Class Tabs
 * @method Tabs setId($string)
 * @method Tabs setTitle($string)
 * @package Mageplaza\RMA\Block\Adminhtml\ShippingLabel\Edit
 */
class Tabs extends WidgetTabs
{
    /**
     * constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $this->setId('shipping_label_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(__('Shipping Label Information'));
    }
}
