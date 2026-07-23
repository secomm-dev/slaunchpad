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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Block\Adminhtml;

use Magento\Backend\Block\Widget\Grid\Container;

/**
 * Class ManageRules
 * @package Mageplaza\ExtraFee\Block\Adminhtml
 */
class ManageRules extends Container
{
    /**
     * constructor
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_controller     = 'adminhtml_rule';
        $this->_blockGroup     = 'Mageplaza_ExtraFee';
        $this->_headerText     = __('Manage Rules');
        $this->_addButtonLabel = __('Add New Rule');

        parent::_construct();
    }
}
