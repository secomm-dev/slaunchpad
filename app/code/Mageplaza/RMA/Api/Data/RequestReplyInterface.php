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
 * Interface RequestReplyInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface RequestReplyInterface
{
    const REPLY_ID             = 'reply_id';
    const REQUEST_ID           = 'request_id';
    const IS_CUSTOMER_NOTIFIED = 'is_customer_notified';
    const IS_VISIBLE_ON_FRONT  = 'is_visible_on_front';
    const AUTHOR_NAME          = 'author_name';
    const TYPE                 = 'type';
    const CONTENT              = 'content';
    const FILES                = 'files';
    const CREATED_AT           = 'created_at';
    const UPLOAD               = 'upload';

    /**
     * @return int
     */
    public function getReplyId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setReplyId($value);

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
    public function getIsCustomerNotified();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setIsCustomerNotified($value);

    /**
     * @return int
     */
    public function getIsVisibleOnFront();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setIsVisibleOnFront($value);

    /**
     * @return string
     */
    public function getAuthorName();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setAuthorName($value);

    /**
     * @return int
     */
    public function getType();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setType($value);

    /**
     * @return string
     */
    public function getContent();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setContent($value);

    /**
     * @return string
     */
    public function getFiles();

    /**
     * @param string[]|string $value
     *
     * @return $this
     */
    public function setFiles($value);

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
}
