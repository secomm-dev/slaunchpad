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
 * Interface RuleInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface RuleInterface
{
    const RULE_ID               = 'rule_id';
    const NAME                  = 'name';
    const DESCRIPTION           = 'description';
    const STATUS                = 'status';
    const WEBSITES              = 'websites';
    const CUSTOMER_GROUP        = 'customer_group';
    const CONDITIONS_SERIALIZED = 'conditions_serialized';
    const REASON                = 'reason';
    const SOLUTION              = 'solution';
    const ADDITIONAL_FIELD      = 'additional_field';
    const PRIORITY              = 'priority';
    const UPDATED_AT            = 'updated_at';
    const CREATED_AT            = 'created_at';

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
     * @return string
     */
    public function getDescription();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setDescription($value);

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
    public function getWebsites();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setWebsites($value);

    /**
     * @return string
     */
    public function getCustomerGroup();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCustomerGroup($value);

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
     * @return \Mageplaza\RMA\Api\Data\ReasonInterface[]
     */
    public function getReason();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setReason($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\SolutionInterface[]
     */
    public function getSolution();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setSolution($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\ItemAdditionalFieldInterface[]
     */
    public function getAdditionalField();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setAdditionalField($value);

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
    public function getUpdatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setUpdatedAt($value);

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
}
