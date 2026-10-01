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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Mageplaza\RMA\Model\Status as StatusModel;

/**
 * Class Reply
 * @package Mageplaza\RMA\Model\ResourceModel
 */
class Status extends AbstractDb
{
    /**
     * Status labels table
     *
     * @var string
     */
    protected $_labelsTable;

    /**
     * Status comments table
     *
     * @var string
     */
    protected $_commentsTable;

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_rma_status', 'status_id');
        $this->_labelsTable = $this->getTable('mageplaza_rma_status_label');
        $this->_commentsTable = $this->getTable('mageplaza_rma_status_comment');
    }

    /**
     * Before save callback
     *
     * @param AbstractModel|StatusModel $object
     *
     * @return $this
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if (is_array($object->getAllowAction())) {
            $object->setAllowAction(implode(',', $object->getAllowAction()));
        }

        return $this;
    }

    /**
     * Save status labels per store
     *
     * @param AbstractModel $object
     *
     * @return AbstractDb|Status
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->saveStatusLabelsRelation($object);
        $this->saveStatusCommentsRelation($object);

        return parent::_afterSave($object);
    }

    /**
     * @param AbstractModel|StatusModel $object
     */
    public function saveStatusLabelsRelation($object)
    {
        if ($object->hasStoreLabels()) {
            $labels = $object->getStoreLabels();
            $this->getConnection()->delete($this->_labelsTable, ['status_id = ?' => $object->getId()]);
            $data = [];
            /** @var array $labels */
            foreach ($labels as $storeId => $label) {
                if (empty($label)) {
                    continue;
                }
                $data[] = ['status_id' => $object->getId(), 'store_id' => $storeId, 'label' => $label];
            }
            if (!empty($data)) {
                $this->getConnection()->insertMultiple($this->_labelsTable, $data);
            }
        }
    }

    /**
     * @param AbstractModel|StatusModel $object
     */
    public function saveStatusCommentsRelation($object)
    {
        if ($object->hasStoreComments()) {
            $comments = $object->getStoreComments();
            $this->getConnection()->delete($this->_commentsTable, ['status_id = ?' => $object->getId()]);
            $data = [];
            /** @var array $comments */
            foreach ($comments as $storeId => $comment) {
                if (empty($comment)) {
                    continue;
                }
                $data[] = ['status_id' => $object->getId(), 'store_id' => $storeId, 'comment' => $comment];
            }
            if (!empty($data)) {
                $this->getConnection()->insertMultiple($this->_commentsTable, $data);
            }
        }
    }

    /**
     * @param StatusModel $status
     * @param string $table
     * @param string $row
     *
     * @return Select
     */
    public function getSelectByStore($status, $table, $row)
    {
        return $this->getConnection()->select()
            ->from(['ss' => $table], [])
            ->where('status_id = ?', $status->getId())
            ->columns([
                'store_id',
                $row,
            ]);
    }

    /**
     * Store labels getter
     *
     * @param StatusModel $status
     *
     * @return array
     */
    public function getStoreLabels($status)
    {
        return $this->getConnection()->fetchPairs($this->getSelectByStore($status, $this->_labelsTable, 'label'));
    }

    /**
     * @param StatusModel $status
     *
     * @return array
     */
    public function getLabelByStore($status)
    {
        return $this->getConnection()->fetchAll($this->getSelectByStore($status, $this->_labelsTable, 'label'));
    }

    /**
     * Store comments getter
     *
     * @param StatusModel $status
     *
     * @return array
     */
    public function getStoreComments($status)
    {
        return $this->getConnection()->fetchPairs($this->getSelectByStore($status, $this->_commentsTable, 'comment'));
    }

    /**
     * @param StatusModel $status
     *
     * @return array
     */
    public function getCommentByStore($status)
    {
        return $this->getConnection()->fetchAll($this->getSelectByStore($status, $this->_commentsTable, 'comment'));
    }

    public function setStatusDataByStore($status, $value, $type)
    {
        $table = $type === 'label' ? $this->_labelsTable : $this->_commentsTable;

        if ($id = $status->getId()) {
            $this->getConnection()->delete($table, 'status_id = ' . $id);
        }

        $data = [];
        foreach ($value as $item) {
            $item->addData(['status_id' => $id]);
            $data[] = $item->getData();
        }

        $this->getConnection()->insertMultiple($table, $data);
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function getStatusIds()
    {
        $statusIds = [];
        $select = $this->getConnection()->select()
            ->from(['ss' => $this->getMainTable()], [])
            ->columns([
                'status_id'
            ]);

        foreach ($this->getConnection()->fetchAll($select) as $item) {
            $statusIds[] = $item['status_id'];
        }

        return $statusIds;
    }
}
