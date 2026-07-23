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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Mageplaza\Lookbook\Api\Data\LookbookInterface;

/**
 * Class Lookbook
 * @package Mageplaza\Lookbook\Model
 */
class Lookbook extends AbstractModel implements IdentityInterface, LookbookInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_lookbookslider_lookbook';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_lookbookslider_lookbook';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_lookbookslider_lookbook';

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Lookbook::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getLookbookId()
    {
        return $this->getData(self::LOOKBOOK_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setLookbookId($value)
    {
        return $this->setData(self::LOOKBOOK_ID, $value);
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
    public function getStoreIds()
    {
        return $this->getData(self::STORE_IDS);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreIds($value)
    {
        return $this->setData(self::STORE_IDS, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getMarkerType()
    {
        return $this->getData(self::MARKER_TYPE);
    }

    /**
     * {@inheritdoc}
     */
    public function setMarkerType($value)
    {
        return $this->setData(self::MARKER_TYPE, $value);
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
    public function getMarker()
    {
        return $this->getData(self::MARKER);
    }

    /**
     * {@inheritdoc}
     */
    public function setMarker($value)
    {
        return $this->setData(self::MARKER, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getWidth()
    {
        return $this->getData(self::WIDTH);
    }

    /**
     * {@inheritdoc}
     */
    public function setWidth($value)
    {
        return $this->setData(self::WIDTH, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeight()
    {
        return $this->getData(self::HEIGHT);
    }

    /**
     * {@inheritdoc}
     */
    public function setHeight($value)
    {
        return $this->setData(self::HEIGHT, $value);
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
}
