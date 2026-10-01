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

namespace Mageplaza\RMA\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Api\Data\StatusInterface;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResourceModel;

/**
 * Class Status
 * @method bool hasStoreLabels()
 * @method bool hasStoreComments()
 * @package Mageplaza\RMA\Model
 */
class Status extends AbstractModel implements StatusInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_rma_status';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_rma_status';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_status';

    /**
     * @var string
     */
    protected $_idFieldName = 'status_id';

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var StatusResourceModel
     */
    protected $_statusResource;

    /**
     * Status constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param StatusResourceModel $statusResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        StatusResourceModel $statusResource,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_storeManager   = $storeManager;
        $this->_statusResource = $statusResource;

        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(StatusResourceModel::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Getter for status labels per store
     *
     * @return array
     */
    public function getStoreLabels()
    {
        if ($this->hasData('store_labels')) {
            return $this->_getData('store_labels');
        }
        $labels = $this->_statusResource->getStoreLabels($this);
        $this->setData('store_labels', $labels);

        return $labels;
    }

    /**
     * Get status label by store
     *
     * @param string|int $storeId
     *
     * @return Phrase|string
     * @throws NoSuchEntityException
     */
    public function getStoreLabel($storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $labels = $this->getStoreLabels();
        if (isset($labels[$storeId])) {
            return $labels[$storeId];
        }

        return __($this->getLabel());
    }

    /**
     * Getter for status comments per store
     *
     * @return array
     */
    public function getStoreComments()
    {
        if ($this->hasData('store_comments')) {
            return $this->_getData('store_comments');
        }
        $comments = $this->_statusResource->getStoreComments($this);
        $this->setData('store_comments', $comments);

        return $comments;
    }

    /**
     * Get status comment by store
     *
     * @param string|int $storeId
     *
     * @return Phrase|string
     * @throws NoSuchEntityException
     */
    public function getStoreComment($storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $comment = $this->getStoreComments();
        if (isset($comment[$storeId])) {
            return $comment[$storeId];
        }

        return __($this->getComment());
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusId()
    {
        return $this->getData(self::STATUS_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatusId($value)
    {
        return $this->setData(self::STATUS_ID, $value);
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
    public function getLabel()
    {
        return $this->getData(self::LABEL);
    }

    /**
     * {@inheritdoc}
     */
    public function setLabel($value)
    {
        return $this->setData(self::LABEL, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getComment()
    {
        return $this->getData(self::COMMENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setComment($value)
    {
        return $this->setData(self::COMMENT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getEnableComment()
    {
        return $this->getData(self::ENABLE_COMMENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setEnableComment($value)
    {
        return $this->setData(self::ENABLE_COMMENT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getIsActive()
    {
        return $this->getData(self::IS_ACTIVE);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsActive($value)
    {
        return $this->setData(self::IS_ACTIVE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * {@inheritdoc}
     */
    public function setDescription($value)
    {
        return $this->setData(self::DESCRIPTION, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowAction()
    {
        return $this->getData(self::ALLOW_ACTION);
    }

    /**
     * {@inheritdoc}
     */
    public function setAllowAction($value)
    {
        return $this->setData(self::ALLOW_ACTION, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setUpdatedAt($value)
    {
        return $this->setData(self::UPDATED_AT, $value);
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
     * {@inheritdoc}
     */
    public function getLabelByStore()
    {
        if (!$this->hasData(self::LABEL_BY_STORE)) {
            $ids = $this->_statusResource->getLabelByStore($this);
            $this->setData(self::LABEL_BY_STORE, $ids);
        }

        return $this->getData(self::LABEL_BY_STORE);
    }

    /**
     * {@inheritdoc}
     */
    public function setLabelByStore($value)
    {
        return $this->setData(self::LABEL_BY_STORE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCommentByStore()
    {
        if (!$this->hasData(self::COMMENT_BY_STORE)) {
            $ids = $this->_statusResource->getCommentByStore($this);
            $this->setData(self::COMMENT_BY_STORE, $ids);
        }

        return $this->getData(self::COMMENT_BY_STORE);
    }

    /**
     * {@inheritdoc}
     */
    public function setCommentByStore($value)
    {
        return $this->setData(self::COMMENT_BY_STORE, $value);
    }
}
