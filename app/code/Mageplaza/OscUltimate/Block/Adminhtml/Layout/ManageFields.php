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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscUltimate\Block\Adminhtml\Layout;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mageplaza\OrderAttributes\Model\Step;

/**
 * Class Order
 * @package Mageplaza\OscUltimate\Block\Adminhtml\Layout
 */
class ManageFields extends AbstractManageFields
{
    const BLOCK_ID = 'mposc-manage-fields';

    /**
     * @return string
     */
    public function getBlockTitle()
    {
        return (string)__('Manage Fields');
    }

    /**
     * @return array|AbstractDb|AbstractCollection|null
     */
    public function getCheckoutStepsOrderAttributes()
    {
        $steps = [];
        if ($this->helper->isEnableOrderAttributes()) {
            $steps = $this->helper->getObject(Step::class);
            $steps = $steps->getCollection()->addFieldToFilter('status', 1);
        }

        return $steps;
    }
}
