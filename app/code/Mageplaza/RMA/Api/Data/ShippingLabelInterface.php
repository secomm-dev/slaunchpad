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
 * Interface ShippingLabelInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface ShippingLabelInterface
{
    const SHIPPING_LABEL_ID       = 'shipping_label_id';
    const LABEL                   = 'label';
    const NAME                    = 'name';
    const STATUS                  = 'status';
    const DESCRIPTION             = 'description';
    const IMAGE                   = 'image';
    const BARCODE                 = 'barcode';
    const INFORMATION             = 'information';
    const CONDITIONS_SERIALIZED   = 'conditions_serialized';
    const RETURN_ADDRESS          = 'return_address';
    const STORE_ID                = 'store_id';
    const PRIORITY                = 'priority';
    const UPDATED_AT              = 'updated_at';
    const CREATED_AT              = 'created_at';
    const SHIPPING_LABEL_BY_STORE = 'shipping_label_by_store';
    const UPLOAD                  = 'upload';

    /**
     * @return int
     */
    public function getShippingLabelId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setShippingLabelId($value);

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setLabel($value);

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
     * @return int
     */
    public function getStatus();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStatus($value);

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setDescription($value);

    /**
     * @return string
     */
    public function getImage();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setImage($value);

    /**
     * @return string
     */
    public function getBarcode();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setBarcode($value);

    /**
     * @return string
     */
    public function getInformation();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setInformation($value);

    /**
     * @return string
     */
    public function getConditionsSerialized();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setConditionsSerialized($value);

    /**
     * @return string
     */
    public function getReturnAddress();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setReturnAddress($value);

    /**
     * @return string
     */
    public function getStoreId();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setStoreId($value);

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setPriority($value);

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
     * @return \Mageplaza\RMA\Api\Data\ShippingStoreLabelsInterface[]
     */
    public function getShippingLabelByStore();

    /**
     * @param \Mageplaza\RMA\Api\Data\ShippingStoreLabelsInterface[] $value
     *
     * @return $this
     */
    public function setShippingLabelByStore($value);

    /**
     * Get media gallery content
     *
     * @return \Magento\Framework\Api\Data\ImageContentInterface|null
     */
    public function getUpload();

    /**
     * Set media gallery content
     *
     * @param \Magento\Framework\Api\Data\ImageContentInterface $value
     *
     * @return $this
     */
    public function setUpload($value);
}
