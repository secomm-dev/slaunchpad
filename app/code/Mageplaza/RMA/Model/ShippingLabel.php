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
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\ResourceModel\Iterator;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Api\Data\ShippingLabelInterface;
use Mageplaza\RMA\Model\Order\Rule;
use Mageplaza\RMA\Model\Order\Rule\Condition\CombineFactory as CondCombineFactory;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;

/**
 * Class ShippingLabel
 * @method bool hasStoreLabels()
 * @package Mageplaza\RMA\Model
 */
class ShippingLabel extends Rule implements ShippingLabelInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_rma_shipping_label';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_rma_shipping_label';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_shipping_label';

    /**
     * @var string
     */
    protected $_idFieldName = 'shipping_label_id';

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ShippingLabelResource
     */
    protected $_shippingLabelResource;

    /**
     * ShippingLabel constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param TimezoneInterface $localeDate
     * @param CondCombineFactory $combineFactory
     * @param Iterator $resourceIterator
     * @param OrderFactory $orderFactory
     * @param StoreManagerInterface $storeManager
     * @param ShippingLabelResource $shippingLabelResource
     * @param AbstractDb|null $resourceCollection
     * @param AbstractResource|null $resource
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        CondCombineFactory $combineFactory,
        Iterator $resourceIterator,
        OrderFactory $orderFactory,
        StoreManagerInterface $storeManager,
        ShippingLabelResource $shippingLabelResource,
        ?AbstractDb $resourceCollection = null,
        ?AbstractResource $resource = null
    ) {
        $this->_storeManager          = $storeManager;
        $this->_shippingLabelResource = $shippingLabelResource;

        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $combineFactory,
            $resourceIterator,
            $orderFactory,
            $resourceCollection,
            $resource
        );
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ShippingLabelResource::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Getter for shipping labels per store
     *
     * @return array
     */
    public function getStoreLabels()
    {
        if ($this->hasData('store_labels')) {
            return $this->_getData('store_labels');
        }
        $labels = $this->_shippingLabelResource->getStoreLabels($this);
        $this->setData('store_labels', $labels);

        return $labels;
    }

    /**
     * Get shipping label by store
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
     * {@inheritdoc}
     */
    public function getShippingLabelId()
    {
        return $this->getData(self::SHIPPING_LABEL_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingLabelId($value)
    {
        return $this->setData(self::SHIPPING_LABEL_ID, $value);
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
    public function getStatus()
    {
        return $this->getData(self::STATUS);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus($value)
    {
        return $this->setData(self::STATUS, $value);
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
    public function getImage()
    {
        return $this->getData(self::IMAGE);
    }

    /**
     * {@inheritdoc}
     */
    public function setImage($value)
    {
        return $this->setData(self::IMAGE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getBarcode()
    {
        return $this->getData(self::BARCODE);
    }

    /**
     * {@inheritdoc}
     */
    public function setBarcode($value)
    {
        return $this->setData(self::BARCODE, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getInformation()
    {
        return $this->getData(self::INFORMATION);
    }

    /**
     * {@inheritdoc}
     */
    public function setInformation($value)
    {
        return $this->setData(self::INFORMATION, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getConditionsSerialized()
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    /**
     * {@inheritdoc}
     */
    public function setConditionsSerialized($value)
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnAddress()
    {
        return $this->getData(self::RETURN_ADDRESS);
    }

    /**
     * {@inheritdoc}
     */
    public function setReturnAddress($value)
    {
        return $this->setData(self::RETURN_ADDRESS, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreId($value)
    {
        return $this->setData(self::STORE_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return $this->getData(self::PRIORITY);
    }

    /**
     * {@inheritdoc}
     */
    public function setPriority($value)
    {
        return $this->setData(self::PRIORITY, $value);
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
    public function getShippingLabelByStore()
    {
        if (!$this->hasData(self::SHIPPING_LABEL_BY_STORE)) {
            $ids = $this->_getResource()->getShippingLabelByStore($this);
            $this->setData(self::SHIPPING_LABEL_BY_STORE, $ids);
        }

        return $this->getData(self::SHIPPING_LABEL_BY_STORE);
    }

    /**
     * {@inheritdoc}
     */
    public function setShippingLabelByStore($value)
    {
        return $this->setData(self::SHIPPING_LABEL_BY_STORE, $value);
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
