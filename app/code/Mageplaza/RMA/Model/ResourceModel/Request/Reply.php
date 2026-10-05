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

namespace Mageplaza\RMA\Model\ResourceModel\Request;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\RMA\Model\Request\Reply as ReplyModel;

/**
 * Class Reply
 * @package Mageplaza\RMA\Model\ResourceModel\Request
 */
class Reply extends AbstractDb
{
    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * Reply constructor.
     *
     * @param Context $context
     * @param DateTime $dateTime
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        $connectionName = null
    ) {
        $this->_dateTime = $dateTime;

        parent::__construct(
            $context,
            $connectionName
        );
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_rma_request_reply', 'reply_id');
    }

    /**
     * Before save callback
     *
     * @param AbstractModel|ReplyModel $object
     *
     * @return $this
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if ($object->getCreatedAt() === null) {
            $object->setCreatedAt($this->_dateTime->date());
        }

        return $this;
    }
}
