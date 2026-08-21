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

namespace Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab\Renderer;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\ResourceModel\Store\Collection;
use Magento\Store\Model\Store;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class Labels
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab\Renderer
 */
class Labels extends Template
{
    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @var string
     */
    protected $_template = 'Magento_Catalog::catalog/product/attribute/labels.phtml';

    /**
     * Labels constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->_registry = $registry;

        parent::__construct($context, $data);
    }

    /**
     * Retrieve frontend labels of attribute for each store
     *
     * @return array
     */
    public function getLabelValues()
    {
        $rule        = $this->_registry->registry('mageplaza_extrafee_rule');
        $values      = [$rule->getName()];
        $storeLabels = $rule->getLabels() ? Data::jsonDecode($rule->getLabels()) : [];
        /** @var Store $store */
        foreach ($this->getStores() as $store) {
            if ($store->getId() != 0) {
                $values[$store->getId()] = isset($storeLabels[$store->getId()]) ? $storeLabels[$store->getId()] : '';
            }
        }

        return $values;
    }

    /**
     * Retrieve stores collection with default store
     *
     * @return Collection
     */
    public function getStores()
    {
        if (!$this->hasStores()) {
            $this->setData('stores', $this->_storeManager->getStores());
        }

        return $this->_getData('stores');
    }
}
