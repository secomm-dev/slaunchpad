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

namespace Mageplaza\RMA\Model\ResourceModel\ShippingLabel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResourceModel;
use Mageplaza\RMA\Model\ShippingLabel;

/**
 * Class Collection
 * @package Mageplaza\RMA\Model\ResourceModel\ShippingLabel
 */
class Collection extends AbstractCollection
{
    /**
     * ID Field Name
     *
     * @var string
     */
    protected $_idFieldName = 'shipping_label_id';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_shipping_label_collection';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'shipping_label_collection';

    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(ShippingLabel::class, ShippingLabelResourceModel::class);
    }
}
