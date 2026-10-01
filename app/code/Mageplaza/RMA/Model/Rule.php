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

use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\Framework\App\ObjectManager;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection;
use Mageplaza\RMA\Api\Data\RuleInterface;
use Mageplaza\RMA\Model\ResourceModel\Rule as RuleResource;

/**
 * Class Rule
 * @package Mageplaza\RMA\Model
 */
class Rule extends AbstractModel implements RuleInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_rma_rule';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_rma_rule';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_rule';

    /**
     * @var string
     */
    protected $_idFieldName = 'rule_id';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(RuleResource::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get rule condition combine model instance
     *
     * @return \Magento\Rule\Model\Condition\Combine
     */
    public function getConditionsInstance()
    {
        return $this->getActionsInstance();
    }

    /**
     * @return Combine|Collection
     */
    public function getActionsInstance()
    {
        return ObjectManager::getInstance()->create(Combine::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleId()
    {
        return $this->getData(self::RULE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setRuleId($value)
    {
        return $this->setData(self::RULE_ID, $value);
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
    public function getWebsites()
    {
        return $this->getData(self::WEBSITES);
    }

    /**
     * {@inheritdoc}
     */
    public function setWebsites($value)
    {
        return $this->setData(self::WEBSITES, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerGroup()
    {
        return $this->getData(self::CUSTOMER_GROUP);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerGroup($value)
    {
        return $this->setData(self::CUSTOMER_GROUP, $value);
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
    public function getAdditionalField()
    {
        return $this->getData(self::ADDITIONAL_FIELD);
    }

    /**
     * {@inheritdoc}
     */
    public function setAdditionalField($value)
    {
        return $this->setData(self::ADDITIONAL_FIELD, $value);
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
}
