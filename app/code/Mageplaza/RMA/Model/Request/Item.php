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

namespace Mageplaza\RMA\Model\Request;

use Magento\Framework\Model\AbstractModel;
use Mageplaza\RMA\Api\Data\RequestItemInterface;
use Mageplaza\RMA\Model\ResourceModel\Request\Item as ItemResource;

/**
 * Class Item
 * @method string getMpQtyRma()
 * @package Mageplaza\RMA\Model\Request
 */
class Item extends AbstractModel implements RequestItemInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_rma_request_item';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_rma_request_item';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_request_item';

    /**
     * @var string
     */
    protected $_idFieldName = 'item_id';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ItemResource::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * {@inheritdoc}
     */
    public function getItemId()
    {
        return $this->getData(self::ITEM_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setItemId($value)
    {
        return $this->setData(self::ITEM_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestId()
    {
        return $this->getData(self::REQUEST_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestId($value)
    {
        return $this->setData(self::REQUEST_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductId()
    {
        return $this->getData(self::PRODUCT_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setProductId($value)
    {
        return $this->setData(self::PRODUCT_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderItemId()
    {
        return $this->getData(self::ORDER_ITEM_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderItemId($value)
    {
        return $this->setData(self::ORDER_ITEM_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getData(self::NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setName($value)
    {
        return $this->setData(self::NAME, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getSku()
    {
        return $this->getData(self::SKU);
    }

    /**
     * {@inheritdoc}
     */
    public function setSku($value)
    {
        return $this->setData(self::SKU, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getQtyRma()
    {
        return $this->getData(self::QTY_RMA);
    }

    /**
     * {@inheritdoc}
     */
    public function setQtyRma($value)
    {
        return $this->setData(self::QTY_RMA, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getPrice()
    {
        return $this->getData(self::PRICE);
    }

    /**
     * {@inheritdoc}
     */
    public function setPrice($value)
    {
        return $this->setData(self::PRICE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriceReturned()
    {
        return $this->getData(self::PRICE_RETURNED);
    }

    /**
     * {@inheritdoc}
     */
    public function setPriceReturned($value)
    {
        return $this->setData(self::PRICE_RETURNED, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getReason()
    {
        return $this->getData(self::REASON);
    }

    /**
     * {@inheritdoc}
     */
    public function setReason($value)
    {
        return $this->setData(self::REASON, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getSolution()
    {
        return $this->getData(self::SOLUTION);
    }

    /**
     * {@inheritdoc}
     */
    public function setSolution($value)
    {
        return $this->setData(self::SOLUTION, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getAdditionalFields()
    {
        return $this->getData(self::ADDITIONAL_FIELDS);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdditionalFields($value)
    {
        return $this->setData(self::ADDITIONAL_FIELDS, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt($value)
    {
        return $this->setData(self::CREATED_AT, $value);
    }
}
