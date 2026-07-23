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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Api\Data;

/**
 * Interface RuleInterface
 * @package Mageplaza\ExtraFee\Api
 */
interface RulesDataInterface
{
    const RULE_ID                 = 'rule_id';
    const NAME                    = 'name';
    const STATUS                  = 'status';
    const STORE_IDS               = 'store_ids';
    const CUSTOMER_GROUPS         = 'customer_groups';
    const PRIORITY                = 'priority';
    const CONDITIONS_SERIALIZED   = 'conditions_serialized';
    const APPLY_TYPE              = 'apply_type';
    const FEE_TYPE                = 'fee_type';
    const AMOUNT                  = 'amount';
    const AREA                    = 'area';
    const DISPLAY_TYPE            = 'display_type';
    const IS_REQUIRED             = 'is_required';
    const FEE_TAX                 = 'fee_tax';
    const SORT_ORDER              = 'sort_order';
    const REFUNDABLE              = 'refundable';
    const STOP_FURTHER_PROCESSING = 'stop_further_processing';
    const LABELS                  = 'labels';
    const OPTIONS                 = 'options';
    const CREATED_AT              = 'created_at';
    const UPDATED_AT              = 'updated_at';

    /**
     * @return int
     */
    public function getRuleId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setRuleId($value);

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
    public function getStoreIds();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setStoreIds($value);

    /**
     * @return string
     */
    public function getCustomerGroups();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCustomerGroups($value);

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
    public function getConditionsSerialized();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setConditionsSerialized($value);

    /**
     * @return int
     */
    public function getApplyType();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setApplyType($value);

    /**
     * @return int
     */
    public function getFeeType();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setFeeType($value);

    /**
     * @return float
     */
    public function getAmount();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setAmount($value);

    /**
     * @return int
     */
    public function getArea();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setArea($value);

    /**
     * @return int
     */
    public function getDisplayType();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setDisplayType($value);

    /**
     * @return int
     */
    public function getIsRequired();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setIsRequired($value);

    /**
     * @return int
     */
    public function getFeeTax();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setFeeTax($value);

    /**
     * @return int
     */
    public function getSortOrder();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setSortOrder($value);

    /**
     * @return int
     */
    public function getRefundable();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setRefundable($value);

    /**
     * @return int
     */
    public function getStopFurtherProcessing();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStopFurtherProcessing($value);

    /**
     * @return string
     */
    public function getLabels();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setLabels($value);

    /**
     * @return string
     */
    public function getOptions();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setOptions($value);

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
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setUpdatedAt($value);
}
