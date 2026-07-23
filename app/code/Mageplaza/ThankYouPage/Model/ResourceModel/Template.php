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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Class Template
 * @package Mageplaza\ThankYouPage\Model\ResourceModel
 */
class Template extends AbstractDb
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_thankyoupage_templates';

    /**
     * Date model
     *
     * @var DateTime
     */
    protected $date;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param DateTime $date
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        DateTime $date,
        $connectionName = null
    ) {
        $this->date = $date;
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('mageplaza_thankyoupage_templates', 'template_id');
    }

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
     * get template by id
     *
     * @param        $templateId
     * @param string $field
     *
     * @return array
     * @throws LocalizedException
     */
    public function getTemplateData($templateId, $field = 'template_id')
    {
        $adapter = $this->getConnection();
        $select  = $adapter->select()
            ->from($this->getMainTable())
            ->where($field . ' = ?', $templateId);

        return $adapter->fetchRow($select);
    }

    /**
     * @param AbstractModel $object
     *
     * @return AbstractDb
     */
    protected function _beforeSave(AbstractModel $object)
    {
        //set default Update At and Create At time post
        $object->setUpdatedAt($this->date->date());
        if ($object->isObjectNew()) {
            $object->setCreatedAt($this->date->date());
        }

        $storeIds = $object->getStoreIds();
        if (is_array($storeIds)) {
            $object->setStoreIds(implode(',', $storeIds));
        }

        $groupIds = $object->getCustomerGroupIds();
        if (is_array($groupIds)) {
            $object->setCustomerGroupIds(implode(',', $groupIds));
        }

        $block = $object->getBlock();
        if (is_array($block)) {
            $object->setBlock(implode(',', $block));
        }

        $slider_additional = $object->getProductSliderAdditional();
        if ($slider_additional && is_array($slider_additional)) {
            $object->setProductSliderAdditional(implode(',', $slider_additional));
        }

        return parent::_beforeSave($object);
    }
}
