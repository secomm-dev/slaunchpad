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

namespace Mageplaza\RMA\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Rule\Model\ResourceModel\AbstractResource;
use Mageplaza\RMA\Model\Rule as RuleModel;

/**
 * Class Rule
 * @package Mageplaza\RMA\Model\ResourceModel
 */
class Rule extends AbstractResource
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_rma_rule', 'rule_id');
    }

    /**
     * @param AbstractModel|RuleModel $object
     *
     * @return $this
     */
    public function _beforeSave(AbstractModel $object)
    {
        if (is_array($object->getWebsites())) {
            $object->setWebsites(implode(',', $object->getWebsites()));
        }

        if (is_array($object->getCustomerGroup())) {
            $object->setCustomerGroup(implode(',', $object->getCustomerGroup()));
        }

        if (is_array($object->getReason())) {
            $object->setReason(implode(',', $object->getReason()));
        }

        if (is_array($object->getSolution())) {
            $object->setSolution(implode(',', $object->getSolution()));
        }

        if (is_array($object->getAdditionalField())) {
            $object->setAdditionalField(implode(',', $object->getAdditionalField()));
        }

        return $this;
    }
}
