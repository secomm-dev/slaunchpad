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

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Class Lookbook
 * @package Mageplaza\Lookbook\Model\ResourceModel
 */
class Lookbook extends AbstractDb
{
    /**
     * Slider relation model
     *
     * @var string
     */
    protected $lookbookSliderTable;

    /**
     * Date model
     *
     * @var DateTime
     */
    protected $date;

    /**
     * Event Manager
     *
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * constructor
     *
     * @param DateTime $date
     * @param ManagerInterface $eventManager
     * @param Context $context
     */
    public function __construct(
        DateTime $date,
        ManagerInterface $eventManager,
        Context $context
    ) {
        $this->date = $date;
        $this->eventManager = $eventManager;

        parent::__construct($context);
        $this->lookbookSliderTable = $this->getTable('mageplaza_lookbookslider_lookbook_slider');
    }

    /**
     * @param $id
     *
     * @return string
     * @throws LocalizedException
     */
    public function getLookbookNameById($id)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()
            ->from($this->getMainTable(), 'name')
            ->where('lookbook_id = :lookbook_id');
        $binds = ['lookbook_id' => (int)$id];

        return $adapter->fetchOne($select, $binds);
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_lookbookslider_lookbook', 'lookbook_id');
    }

    /**
     * @param AbstractModel $object
     *
     * @return $this|AbstractDb
     */
    protected function _beforeSave(AbstractModel $object)
    {
        //set default Update At and Create At time post
        $object->setUpdatedAt($this->date->date());
        if ($object->isObjectNew()) {
            $object->setCreatedAt($this->date->date());
        }

        $storeIds = $object->getData('store_ids');
        if (is_array($storeIds)) {
            $object->setData('store_ids', implode(',', $storeIds));
        }

        return $this;
    }
}
