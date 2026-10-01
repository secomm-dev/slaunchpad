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

use Magento\Framework\DB\Select;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Mageplaza\RMA\Model\ShippingLabel as ShippingLabelModel;

/**
 * Class ShippingLabel
 * @package Mageplaza\RMA\Model\ResourceModel
 */
class ShippingLabel extends AbstractDb
{
    /**
     * Shipping Label labels table
     *
     * @var string
     */
    protected $_labelsTable;

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_rma_shipping_label', 'shipping_label_id');
        $this->_labelsTable = $this->getTable('mageplaza_rma_shipping_label_label');
    }

    /**
     * @param AbstractModel|ShippingLabelModel $object
     *
     * @return $this
     */
    public function _beforeSave(AbstractModel $object)
    {
        if (is_array($object->getStoreId())) {
            $object->setStoreId(implode(',', $object->getStoreId()));
        }
        if (is_array($object->getInformation())) {
            $object->setInformation(implode(',', $object->getInformation()));
        }

        return $this;
    }

    /**
     * Save status labels per store
     *
     * @param AbstractModel $object
     *
     * @return AbstractDb
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->saveShippingLabelsRelation($object);

        return parent::_afterSave($object);
    }

    /**
     * @param AbstractModel|ShippingLabelModel $object
     */
    public function saveShippingLabelsRelation($object)
    {
        if ($object->hasStoreLabels()) {
            $labels = $object->getStoreLabels();
            $this->getConnection()->delete($this->_labelsTable, ['shipping_label_id = ?' => $object->getId()]);
            $data = [];
            /** @var array $labels */
            foreach ($labels as $storeId => $label) {
                if (empty($label)) {
                    continue;
                }
                $data[] = ['shipping_label_id' => $object->getId(), 'store_id' => $storeId, 'label' => $label];
            }
            if (!empty($data)) {
                $this->getConnection()->insertMultiple($this->_labelsTable, $data);
            }
        }
    }

    /**
     * @param ShippingLabelModel $shippingLabel
     *
     * @return Select
     */
    public function getSelectStoreLabels($shippingLabel)
    {
        return $this->getConnection()->select()
            ->from(['ssl' => $this->_labelsTable], [])
            ->where('shipping_label_id = ?', $shippingLabel->getId())
            ->columns([
                'store_id',
                'label',
            ]);
    }

    /**
     * Store labels getter
     *
     * @param ShippingLabelModel $shippingLabel
     *
     * @return array
     */
    public function getStoreLabels($shippingLabel)
    {
        return $this->getConnection()->fetchPairs($this->getSelectStoreLabels($shippingLabel));
    }

    /**
     * @param ShippingLabelModel $shippingLabel
     *
     * @return array
     */
    public function getShippingLabelByStore($shippingLabel)
    {
        return $this->getConnection()->fetchAll($this->getSelectStoreLabels($shippingLabel));
    }

    public function setShippingLabelByStore($shippingLabel, $value)
    {
        if ($id = $shippingLabel->getId()) {
            $this->getConnection()->delete($this->_labelsTable, 'shipping_label_id = ' . $id);
        }

        $data = [];
        foreach ($value as $item) {
            $item->addData(['shipping_label_id' => $id]);
            $data[] = $item->getData();
        }

        $this->getConnection()->insertMultiple($this->_labelsTable, $data);
    }
}
