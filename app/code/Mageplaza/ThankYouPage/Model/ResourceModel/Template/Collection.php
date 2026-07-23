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

namespace Mageplaza\ThankYouPage\Model\ResourceModel\Template;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 * @package Mageplaza\ThankYouPage\Model\ResourceModel\Template
 */
class Collection extends AbstractCollection
{
    /**
     * ID Field Name
     *
     * @var string
     */
    protected $_idFieldName = 'template_id';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_thankyoupage_template_collection';

    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'template_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Mageplaza\ThankYouPage\Model\Template', 'Mageplaza\ThankYouPage\Model\ResourceModel\Template');
    }

    /**
     * Get SQL for get record count.
     * Extra GROUP BY strip added.
     *
     * @return Select
     */
    public function getSelectCountSql()
    {
        $countSelect = parent::getSelectCountSql();
        $countSelect->reset(Select::GROUP);

        return $countSelect;
    }

    /**
     * @param $customerGroup
     * @param $storeId
     *
     * @return $this
     */
    public function addActiveFilter($customerGroup = null, $storeId = null)
    {
        $this->addFieldToFilter('status', true)->setOrder('priority', Select::SQL_DESC);

        if (isset($customerGroup)) {
            $this->getSelect()
                ->where('FIND_IN_SET(?, customer_group_ids)', $customerGroup);
        }
        if (isset($storeId)) {
            $this->getSelect()
                ->where('FIND_IN_SET(0, store_ids) OR FIND_IN_SET(?, store_ids)', $storeId);
        }

        return $this;
    }

    /**
     * @param $type
     *
     * @return $this
     */
    public function addPageTypeFilter($type)
    {
        $this->addFieldToFilter('page_type', $type);

        return $this;
    }

    /**
     * @param $type
     *
     * @return $this
     */
    public function addTemplateTypeFilter($type)
    {
        $this->addFieldToFilter('template_type', $type);

        return $this;
    }
}
