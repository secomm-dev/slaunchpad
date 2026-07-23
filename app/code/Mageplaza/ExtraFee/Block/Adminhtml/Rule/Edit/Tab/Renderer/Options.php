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
use Magento\Backend\Block\Widget;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\FeeType;
use Mageplaza\ExtraFee\Model\Config\Source\FeeTypeItem;

/**
 * Class Options
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Rule\Edit\Tab\Renderer
 */
class Options extends Widget implements RendererInterface
{
    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @var string
     */
    protected $_template = 'Mageplaza_ExtraFee::rule/options.phtml';

    /**
     * @var string
     */
    protected $_nameInLayout = 'mp_extra_fee_options';

    /**
     * @var FeeType
     */
    protected $feeType;

    /**
     * @var FeeTypeItem
     */
    protected $feeTypeItem;

    /**
     * Options constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FeeType $feeType
     * @param FeeTypeItem $feeTypeItem
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FeeType $feeType,
        FeeTypeItem $feeTypeItem,
        array $data = []
    ) {
        $this->_registry   = $registry;
        $this->feeType     = $feeType;
        $this->feeTypeItem = $feeTypeItem;

        parent::__construct($context, $data);
    }

    /**
     * @return array
     */
    public function getFeeType()
    {
        $feeType = $this->feeType->toOptionArray();

        $rule = $this->_registry->registry('mageplaza_extrafee_rule');
        if ($rule->getApplyFor() == 2) {
            $feeType = $this->feeTypeItem->toOptionArray();
        }

        return $feeType;
    }

    /**
     * Retrieve attribute option values if attribute input type select or multiselect
     *
     * @return array
     */
    public function getOptionValues()
    {
        $values  = [];
        $rule    = $this->_registry->registry('mageplaza_extrafee_rule');
        $options = Data::jsonDecode($rule->getOptions());
        if (!empty($options['option'])) {
            $values = $this->_prepareOptionValues($options);
        }

        return $values;
    }

    /**
     * @param $options
     *
     * @return array
     */
    protected function _prepareOptionValues($options)
    {
        $defaultValues = $options['default'] ?: [];
        $inputType     = 'radio';
        $order         = $options['option']['order'];
        $values        = [];
        if (!empty($options['option']['value']) && is_array($options['option']['value'])) {
            foreach ($options['option']['value'] as $id => $option) {
                $bunch = $this->_prepareAttributeOptionValues(
                    $id,
                    $option,
                    $inputType,
                    $defaultValues,
                    $order[$id]
                );
                foreach ($bunch as $value) {
                    $values[] = new DataObject($value);
                }
            }
        }

        return $values;
    }

    /**
     * Prepare option values of user defined attribute
     *
     * @param $id
     * @param array|Option $option
     * @param string $inputType
     * @param array $defaultValues
     * @param $sortOrder
     *
     * @return array
     */
    protected function _prepareAttributeOptionValues($id, $option, $inputType, $defaultValues, $sortOrder)
    {
        $optionId = $id;

        $value['checked']    = in_array($optionId, $defaultValues) ? 'checked="checked"' : '';
        $value['intype']     = $inputType;
        $value['id']         = $optionId;
        $value['sort_order'] = $sortOrder;
        $value['type']       = $option['type'];
        $value['amount']     = $option['amount'];

        foreach ($this->getStores() as $store) {
            $storeId                   = $store->getId();
            $value['store' . $storeId] = isset($option[$storeId]) ? $option[$storeId] : '';
        }

        return [$value];
    }

    /**
     * Retrieve stores collection with default store
     *
     * @return array
     */
    public function getStores()
    {
        if (!$this->hasStores()) {
            $this->setData('stores', $this->_storeManager->getStores(true));
        }

        return $this->_getData('stores');
    }

    /**
     * Returns stores sorted by Sort Order
     *
     * @return array
     */
    public function getStoresSortedBySortOrder()
    {
        $stores = $this->getStores();
        if (is_array($stores)) {
            usort($stores, function ($storeA, $storeB) {
                if ($storeA->getSortOrder() == $storeB->getSortOrder()) {
                    return $storeA->getId() < $storeB->getId() ? -1 : 1;
                }

                return ($storeA->getSortOrder() < $storeB->getSortOrder()) ? -1 : 1;
            });
        }

        return $stores;
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        return $this->toHtml();
    }
}
