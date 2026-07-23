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
interface ListInterface
{
    const RULES            = 'rules';
    const SELECTED_OPTIONS = 'select_options';

    /**
     * @return \Mageplaza\ExtraFee\Api\Data\Rules\AreaInterface
     */
    public function getRules();

    /**
     * @param \Magento\Framework\DataObject $value
     *
     * @return $this
     */
    public function setRules($value);

    /**
     * @return string
     */
    public function getSelectedOptions();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setSelectedOptions($value);
}
