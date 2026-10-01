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

namespace Mageplaza\RMA\Api\Data;

/**
 * Interface RequestItemInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface RequestItemInterface
{
    const ITEM_ID           = 'item_id';
    const REQUEST_ID        = 'request_id';
    const PRODUCT_ID        = 'product_id';
    const ORDER_ITEM_ID     = 'order_item_id';
    const NAME              = 'name';
    const SKU               = 'sku';
    const QTY_RMA           = 'qty_rma';
    const PRICE             = 'price';
    const PRICE_RETURNED    = 'price_returned';
    const REASON            = 'reason';
    const SOLUTION          = 'solution';
    const ADDITIONAL_FIELDS = 'additional_fields';
    const CREATED_AT        = 'created_at';

    /**
     * @return int
     */
    public function getItemId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setItemId($value);

    /**
     * @return int
     */
    public function getRequestId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setRequestId($value);

    /**
     * @return int
     */
    public function getProductId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setProductId($value);

    /**
     * @return int
     */
    public function getOrderItemId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setOrderItemId($value);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setName($value);

    /**
     * @return string
     */
    public function getSku();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setSku($value);

    /**
     * @return float
     */
    public function getQtyRma();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setQtyRma($value);

    /**
     * @return float
     */
    public function getPrice();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setPrice($value);

    /**
     * @return float
     */
    public function getPriceReturned();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setPriceReturned($value);

    /**
     * @return string
     */
    public function getReason();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setReason($value);

    /**
     * @return string
     */
    public function getSolution();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setSolution($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\ItemAdditionalFieldInterface[]
     */
    public function getAdditionalFields();

    /**
     * @param \Mageplaza\RMA\Api\Data\ItemAdditionalFieldInterface[] $value
     *
     * @return $this
     */
    public function setAdditionalFields($value);

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCreatedAt($value);
}
