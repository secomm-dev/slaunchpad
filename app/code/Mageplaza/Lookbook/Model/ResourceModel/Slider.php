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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Zend_Db_Expr;

/**
 * Class Slider
 * @package Mageplaza\Lookbook\Model\ResourceModel
 */
class Slider extends AbstractDb
{
    /**
     * @var DateTime
     */
    protected $date;

    /**
     * Slider constructor.
     *
     * @param Context $context
     * @param DateTime $date
     */
    public function __construct(
        Context $context,
        DateTime $date
    ) {
        $this->date = $date;
        parent::__construct($context);
    }

    /**
     * Retrieve Lookbook link_id by link lookbook id
     *
     * @param int $parentId
     * @param int $lookbookId
     *
     * @return string
     */
    public function getLookbookLinkId($parentId, $lookbookId)
    {
        $connection = $this->getConnection();

        $bind = [
            ':slider_id' => (int)$parentId,
            ':lookbook_id' => (int)$lookbookId
        ];
        $select = $connection->select()->from(
            $this->getTable('mageplaza_lookbookslider_slider_lookbook'),
            ['link_id']
        )->where(
            'slider_id = :slider_id'
        )->where(
            'lookbook_id = :lookbook_id'
        );

        return $connection->fetchOne($select, $bind);
    }

    /**
     * Retrieve parent ids array by required child
     *
     * @param int|array $childId
     *
     * @return string[]
     */
    public function getParentIdsByChild($childId)
    {
        $parentIds = [];
        $connection = $this->getConnection();
        $select = $connection->select()->from(
            $this->getTable('mageplaza_lookbookslider_slider_lookbook'),
            ['slider_id', 'lookbook_id']
        )->where(
            'lookbook_id IN(?)',
            $childId
        );

        $result = $connection->fetchAll($select);
        foreach ($result as $row) {
            $parentIds[] = $row['slider_id'];
        }

        return $parentIds;
    }

    /**
     * Retrieve Required children ids
     * Return grouped array, ex array(
     *   group => array(ids)
     * )
     *
     * @param int $parentId
     *
     * @return array
     */
    public function getChildrenIds($parentId)
    {
        $connection = $this->getConnection();
        $childrenIds = [];
        $bind = [':slider_id' => (int)$parentId];
        $select = $connection->select()->from(
            ['l' => $this->getTable('mageplaza_lookbookslider_slider_lookbook')],
            ['lookbook_id']
        )->where(
            'slider_id = :slider_id'
        )->order('position');

        $result = $connection->fetchAll($select, $bind);
        foreach ($result as $row) {
            $childrenIds[] = $row['lookbook_id'];
        }

        return $childrenIds;
    }

    /**
     * @param int $parentId
     *
     * @return array
     */
    public function getLookbooksData($parentId)
    {
        $connection = $this->getConnection();
        $bind = [':slider_id' => (int)$parentId];
        $select = $connection->select()->from(
            ['l' => $this->getTable('mageplaza_lookbookslider_slider_lookbook')],
            ['lookbook_id', 'position']
        )->where(
            'slider_id = :slider_id'
        )->order('position');

        return $connection->fetchAll($select, $bind);
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_lookbookslider_slider', 'slider_id');
    }

    /**
     * @param AbstractModel $object
     *
     * @return Slider
     */
    protected function _beforeSave(AbstractModel $object)
    {
        //set default Update At and Create At time post
        $object->setUpdatedAt($this->date->date());
        if ($object->isObjectNew()) {
            $object->setCreatedAt($this->date->date());
        }

        return parent::_beforeSave($object);
    }

    /**
     * @param AbstractModel $object
     *
     * @return Slider
     */
    protected function _afterSave(AbstractModel $object)
    {
        $data = $object->getData('lookbook');
        $position = 0;

        if ($object->getId() && $this->hasLookbookLinks($object->getId())) {
            foreach ($this->getLinkIdBySliderId($object->getId()) as $linkId) {
                $this->deleteLookbookLink($linkId);
            }
        }

        if (!$data || !isset($data['data']['lookbook'])) {
            return parent::_afterSave($object);
        }

        $lookbookData = $data['data']['lookbook'];
        $lookbookList = [];
        // Set array position as a fallback position if necessary
        foreach ($lookbookData['assigned_lookbooks'] as $item) {
            if (!$this->hasPosition($item)) {
                $item['position'] = ++$position;
            }
            $lookbookList[$item['lookbook_id']] = $item;
        }
        $this->saveAssignedLookbook($object->getId(), $lookbookList);

        return parent::_afterSave($object);
    }

    /**
     * Check if product has links.
     *
     * @param int $parentId Slider Id
     *
     * @return bool
     */
    public function hasLookbookLinks($parentId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from(
            $this->getTable('mageplaza_lookbookslider_slider_lookbook'),
            ['count' => new Zend_Db_Expr('COUNT(*)')]
        )->where('slider_id = :slider_id');

        return $connection->fetchOne($select, ['slider_id' => $parentId]) > 0;
    }

    /**
     * @param int $parentId
     *
     * @return array
     */
    public function getLinkIdBySliderId($parentId)
    {
        $connection = $this->getConnection();
        $data = [];
        $bind = [':slider_id' => (int)$parentId];
        $select = $connection->select()->from(
            ['l' => $this->getTable('mageplaza_lookbookslider_slider_lookbook')],
            ['link_id']
        )->where(
            'slider_id = :slider_id'
        );

        $result = $connection->fetchAll($select, $bind);
        foreach ($result as $row) {
            $data[] = $row['link_id'];
        }

        return $data;
    }

    /**
     * Delete lookbook link by link_id
     *
     * @param int $linkId
     *
     * @return int
     */
    public function deleteLookbookLink($linkId)
    {
        return $this->getConnection()->delete(
            $this->getTable('mageplaza_lookbookslider_slider_lookbook'),
            ['link_id = ?' => $linkId]
        );
    }

    /**
     * Check if at least one link without position
     *
     * @param array $links
     *
     * @return bool
     */
    protected function hasPosition(array $links)
    {
        if (!array_key_exists('position', $links)) {
            return false;
        }

        return true;
    }

    /**
     * Save Lookbook Links process
     *
     * @param int $parentId
     * @param array $data
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function saveAssignedLookbook($parentId, $data)
    {
        if (!is_array($data)) {
            $data = [];
        }

        $connection = $this->getConnection();
        $bind = [':slider_id' => (int)$parentId];
        $select = $connection->select()->from(
            $this->getTable('mageplaza_lookbookslider_slider_lookbook'),
            ['lookbook_id', 'link_id']
        )->where(
            'slider_id = :slider_id'
        );

        $links = $connection->fetchPairs($select, $bind);
        foreach ($data as $lookbookId => $linkInfo) {
            if (!isset($links[$lookbookId])) {
                $bind = [
                    'slider_id' => $parentId,
                    'lookbook_id' => $lookbookId,
                    'position' => $linkInfo['position']
                ];
                $connection->insert($this->getTable('mageplaza_lookbookslider_slider_lookbook'), $bind);
            }
        }

        return $this;
    }
}
