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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Plugin\Model\ResourceModel\Grid;

use Magento\Customer\Model\ResourceModel\Grid\Collection as CustomerGrid;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Select;
use Mageplaza\AbandonedCart\Helper\Data;
use Zend_Db_Select_Exception;

/**
 * Class Collection
 * @package Mageplaza\AbandonedCart\Plugin\Model\ResourceModel\Grid
 */
class Collection
{
    /**
     * Flag to check whether the join query is added or not
     *
     * @var bool $isJoint
     */
    protected $isJoin = false;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * Collection constructor.
     *
     * @param RequestInterface $request
     * @param Data $helperData
     */
    public function __construct(
        RequestInterface $request,
        Data $helperData
    ) {
        $this->request    = $request;
        $this->helperData = $helperData;
    }

    /**
     * @param CustomerGrid $subject
     * @param object|null $result
     *
     * @return Select
     * @throws Zend_Db_Select_Exception
     */
    public function afterGetSelect(CustomerGrid $subject, $result)
    {
        $customerTable = $subject->getTable('customer_entity');
        /** @var $result Select */
        if ($result && $result->getPart('from') && !$this->isJoin) {
            $result = $result->joinLeft(
                $customerTable,
                "main_table.entity_id = {$customerTable}.entity_id",
                [
                    'mp_ace_blacklist' => "{$customerTable}.mp_ace_blacklist",
                ]
            );

            $this->isJoin = true;
        }

        return $result;
    }

    /**
     * @param CustomerGrid $subject
     */
    public function beforeGetItems(CustomerGrid $subject)
    {
        $actionName = $this->request->getActionName();
        if ($actionName === 'gridToCsv') {
            $customerTable = $subject->getTable('customer_entity');
            $subject->getSelect()->joinLeft(
                $customerTable,
                "main_table.entity_id = {$customerTable}.entity_id",
                [
                    'mp_ace_blacklist' => "{$customerTable}.mp_ace_blacklist",
                ]
            );
        }
    }

    /**
     * @param CustomerGrid $subject
     * @param string $field
     * @param null $condition
     *
     * @return array
     */
    public function beforeAddFieldToFilter(CustomerGrid $subject, $field, $condition = null)
    {
        if ($field === 'mp_ace_blacklist') {
            $customerTable = $subject->getTable('customer_entity');
            $field = $customerTable . '.' . $field;
        }

        return [$field, $condition];
    }
}
