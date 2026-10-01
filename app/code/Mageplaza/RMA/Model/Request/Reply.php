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
use Mageplaza\RMA\Api\Data\RequestReplyInterface;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;

/**
 * Class Reply
 * // * @method Reply setIsCustomerNotified($isCustomerNotified)
 * // * @method Reply setCreatedAt($createdAt)
 * // * @method string getCreatedAt()
 * // * @method string getType()
 * // * @method string getContent()
 * // * @method string getFiles()
 * // * @method string getAuthorName()
 * @method string getAuthorEmail()
 * // * @method string getIsCustomerNotified()
 * @package Mageplaza\RMA\Model\Request
 */
class Reply extends AbstractModel implements RequestReplyInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_rma_request_reply';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_rma_request_reply';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_request_reply';

    /**
     * @var string
     */
    protected $_idFieldName = 'reply_id';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ReplyResource::class);
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
    public function getReplyId()
    {
        return $this->getData(self::REPLY_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setReplyId($value)
    {
        return $this->setData(self::REPLY_ID, $value);
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
    public function getIsCustomerNotified()
    {
        return $this->getData(self::IS_CUSTOMER_NOTIFIED);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsCustomerNotified($value)
    {
        return $this->setData(self::IS_CUSTOMER_NOTIFIED, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getIsVisibleOnFront()
    {
        return $this->getData(self::IS_VISIBLE_ON_FRONT);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsVisibleOnFront($value)
    {
        return $this->setData(self::IS_VISIBLE_ON_FRONT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorName()
    {
        return $this->getData(self::AUTHOR_NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthorName($value)
    {
        return $this->setData(self::AUTHOR_NAME, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function setType($value)
    {
        return $this->setData(self::TYPE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return $this->getData(self::CONTENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setContent($value)
    {
        return $this->setData(self::CONTENT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles()
    {
        return $this->getData(self::FILES);
    }

    /**
     * {@inheritdoc}
     */
    public function setFiles($value)
    {
        return $this->setData(self::FILES, $value);
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

    /**
     * @inheritDoc
     */
    public function getUpload()
    {
        return $this->getData(self::UPLOAD);
    }

    /**
     * @inheritDoc
     */
    public function setUpload($value)
    {
        return $this->setData(self::UPLOAD, $value);
    }
}
