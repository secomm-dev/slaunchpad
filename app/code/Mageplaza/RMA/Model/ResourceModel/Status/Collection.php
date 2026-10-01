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

namespace Mageplaza\RMA\Model\ResourceModel\Status;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResourceModel;
use Mageplaza\RMA\Model\Status;

/**
 * Class Collection
 * @package Mageplaza\RMA\Model\ResourceModel\Status
 */
class Collection extends AbstractCollection
{
    /**
     * ID Field Name
     *
     * @var string
     */
    protected $_idFieldName = 'status_id';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_status_collection';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'status_collection';

    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(Status::class, StatusResourceModel::class);
    }
}
