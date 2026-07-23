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
 * Interface MpExtraFeeInterface
 * @package Mageplaza\ExtraFee\Api\Data
 */
interface MpExtraFeeInterface
{
    const CODE                = 'code';
    const TITLE               = 'title';
    const LABEL               = 'label';
    const VALUE               = 'value';
    const VALUE_EXCL_TAX      = 'value_excl_tax';
    const VALUE_INCL_TAX      = 'value_incl_tax';
    const BASE_VALUE          = 'base_value';
    const BASE_VALUE_INCL_TAX = 'base_value_incl_tax';
    const RF                  = 'rf';
    const DISPLAY_AREA        = 'display_area';
    const APPLY_TYPE          = 'apply_type';
    const RULE_LABEL          = 'rule_label';

    /**
     * @return string
     */
    public function getCode();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCode($value);

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setTitle($value);

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setLabel($value);

    /**
     * @return float
     */
    public function getValue();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setValue($value);

    /**
     * @return float
     */
    public function getValueExclTax();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setValueExclTax($value);

    /**
     * @return float
     */
    public function getValueInclTax();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setValueInclTax($value);

    /**
     * @return float
     */
    public function getBaseValue();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setBaseValue($value);

    /**
     * @return float
     */
    public function getBaseValueInclTax();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setBaseValueInclTax($value);

    /**
     * @return float
     */
    public function getRf();

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setRf($value);

    /**
     * @return int
     */
    public function getDisplayArea();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setDisplayArea($value);

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
     * @return string
     */
    public function getRuleLabel();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setRuleLabel($value);
}
