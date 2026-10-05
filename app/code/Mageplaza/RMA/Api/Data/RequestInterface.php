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
 * Interface RequestInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface RequestInterface
{
    const REQUEST_ID             = 'request_id';
    const ORDER_ID               = 'order_id';
    const ORDER_INCREMENT_ID     = 'order_increment_id';
    const INCREMENT_ID           = 'increment_id';
    const STATUS_ID              = 'status_id';
    const IS_CANCELED            = 'is_canceled';
    const STORE_ID               = 'store_id';
    const COMMENT                = 'comment';
    const FILES                  = 'files';
    const LAST_RESPONDED_BY      = 'last_responded_by';
    const CUSTOMER_EMAIL         = 'customer_email';
    const UPDATED_AT             = 'updated_at';
    const CREATED_AT             = 'created_at';
    const REQUEST_ITEM           = 'request_item';
    const REQUEST_REPLY          = 'request_reply';
    const REQUEST_SHIPPING_LABEL = 'request_shipping_label';
    const UPLOAD                 = 'upload';
    const GUEST_DATA             = 'guest_data';
    const REASON                 = 'reason';
    const SOLUTION               = 'solution';
    const ADDITIONAL_FIELDS      = 'additional_fields';

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
    public function getOrderId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setOrderId($value);

    /**
     * @return string
     */
    public function getOrderIncrementId();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setOrderIncrementId($value);

    /**
     * @return string
     */
    public function getIncrementId();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setIncrementId($value);

    /**
     * @return int
     */
    public function getStatusId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStatusId($value);

    /**
     * @return int
     */
    public function getIsCanceled();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setIsCanceled($value);

    /**
     * @return int
     */
    public function getStoreId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStoreId($value);

    /**
     * @return string
     */
    public function getComment();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setComment($value);

    /**
     * @return string
     */
    public function getFiles();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setFiles($value);

    /**
     * @return string
     */
    public function getLastRespondedBy();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setLastRespondedBy($value);

    /**
     * @return string
     */
    public function getCustomerEmail();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCustomerEmail($value);

    /**
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setUpdatedAt($value);

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

    /**
     * @return \Mageplaza\RMA\Api\Data\RequestItemInterface[]
     */
    public function getRequestItem();

    /**
     * @param \Mageplaza\RMA\Api\Data\RequestItemInterface[] $value
     *
     * @return $this
     */
    public function setRequestItem($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\RequestReplyInterface[]
     */
    public function getRequestReply();

    /**
     * @param \Mageplaza\RMA\Api\Data\RequestReplyInterface[] $value
     *
     * @return $this
     */
    public function setRequestReply($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\RequestShippingLabelInterface[]
     */
    public function getRequestShippingLabel();

    /**
     * @param \Mageplaza\RMA\Api\Data\RequestShippingLabelInterface[] $value
     *
     * @return $this
     */
    public function setRequestShippingLabel($value);

    /**
     * Get media gallery content
     *
     * @return \Magento\Framework\Api\Data\ImageContentInterface[]|null
     */
    public function getUpload();

    /**
     * Set media gallery content
     *
     * @param \Magento\Framework\Api\Data\ImageContentInterface[] $value
     *
     * @return $this
     */
    public function setUpload($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\GuestDataInterface|null
     */
    public function getGuestData();

    /**
     * @param \Mageplaza\RMA\Api\Data\GuestDataInterface $value
     *
     * @return $this
     */
    public function setGuestData($value);

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
}
